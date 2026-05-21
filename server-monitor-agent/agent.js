const si = require('systeminformation');
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const net = require('net');
const { execFile } = require('child_process');
const packageJson = require('./package.json');

class IisLogCollector {
    constructor(config, sendSummary, reportError) {
        this.config = config;
        this.sendSummary = sendSummary;
        this.reportError = reportError;
        this.isRunning = false;
        this.isScanning = false;
        this.timer = null;
        this.state = this.loadState();
        this.health = {
            enabled: false,
            last_scan_at: null,
            files_seen: 0,
            files_read: 0,
            lines_read: 0,
            summaries_sent: 0,
            last_error: null,
            state_file_path: this.statePath(),
        };
    }

    get settings() {
        const source = this.config.iisLogs || {};

        return {
            enabled: Boolean(source.enabled),
            paths: Array.isArray(source.paths) ? source.paths.filter(Boolean) : [],
            scanIntervalSeconds: Number(source.scanIntervalSeconds || 60),
            summaryOnly: source.summaryOnly !== false,
            maxLinesPerRun: Number(source.maxLinesPerRun || 5000),
            sendRawSamples: Boolean(source.sendRawSamples),
            sampleLimit: Number(source.sampleLimit || 20),
            allowlist: {
                ipAddresses: Array.isArray(source.allowlist?.ipAddresses) ? source.allowlist.ipAddresses.filter(Boolean) : [],
                urlPathContains: Array.isArray(source.allowlist?.urlPathContains) ? source.allowlist.urlPathContains.filter(Boolean) : [],
                userAgents: Array.isArray(source.allowlist?.userAgents) ? source.allowlist.userAgents.filter(Boolean) : [],
            },
        };
    }

    start() {
        this.health.enabled = this.settings.enabled && this.settings.paths.length > 0;

        if (!this.settings.enabled || this.settings.paths.length === 0) {
            return;
        }

        this.isRunning = true;
        this.schedule(1000);
    }

    stop() {
        this.isRunning = false;
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }

    schedule(delayMs) {
        if (!this.isRunning) {
            return;
        }

        this.timer = setTimeout(async () => {
            await this.runOnce();
            this.schedule(Math.max(5, this.settings.scanIntervalSeconds) * 1000);
        }, delayMs);
    }

    async runOnce() {
        if (this.isScanning) {
            return;
        }

        this.isScanning = true;
        this.health.enabled = this.settings.enabled && this.settings.paths.length > 0;
        this.health.last_scan_at = new Date().toISOString();

        try {
            const summary = await this.collectSummary();
            this.saveState();
            summary.collector_health = this.healthSnapshot();

            if (summary.lines_scanned > 0 || summary.parser_errors.length > 0 || summary.collector_health) {
                const sent = await this.sendSummary(summary);
                if (sent !== false) {
                    this.health.summaries_sent++;
                }
            }
        } catch (error) {
            this.recordHealthError(`IIS log parsing failed: ${error.message}`);
            await this.sendErrorSummary();
        } finally {
            this.isScanning = false;
        }
    }

    statePath() {
        const configured = this.config.iisLogs?.statePath;
        if (configured) {
            return configured;
        }

        const configDir = this.config._configPath ? path.dirname(this.config._configPath) : process.cwd();
        return path.join(configDir, 'iis-log-state.json');
    }

    loadState() {
        try {
            const statePath = this.statePath();
            if (fs.existsSync(statePath)) {
                return JSON.parse(fs.readFileSync(statePath, 'utf8'));
            }
        } catch (error) {
            this.reportError(`IIS log state load failed: ${error.message}`);
        }

        return { files: {} };
    }

    saveState() {
        try {
            fs.writeFileSync(this.statePath(), JSON.stringify(this.state, null, 2));
        } catch (error) {
            this.reportError(`IIS log state save failed: ${error.message}`);
        }
    }

    async collectSummary() {
        const settings = this.settings;
        this.health.files_seen = 0;
        this.health.files_read = 0;
        this.health.lines_read = 0;
        this.health.last_error = null;

        const files = this.discoverLogFiles(settings.paths);
        const summary = this.emptySummary();
        let remainingLines = Math.max(1, settings.maxLinesPerRun);

        summary.files_scanned = files.length;
        this.health.files_seen = files.length;

        for (const file of files) {
            if (remainingLines <= 0) {
                break;
            }

            try {
                const result = this.readNewLines(file, remainingLines);
                remainingLines -= result.lines.length;
                summary.lines_scanned += result.lines.length;
                this.health.files_read++;
                this.health.lines_read += result.lines.length;
                this.processLines(result.lines, summary, settings, file, result.fields);
            } catch (error) {
                const message = `${file}: ${error.message}`;
                summary.parser_errors.push(message);
                this.recordHealthError(message);
            }
        }

        summary.top_ips = this.topValues(summary._ipCounts, 10);
        summary.top_urls = this.topValues(summary._urlCounts, 10);
        delete summary._ipCounts;
        delete summary._urlCounts;

        return summary;
    }

    async sendErrorSummary() {
        try {
            const summary = this.emptySummary();
            summary.parser_errors.push(this.health.last_error || 'IIS collector failed');
            summary.collector_health = this.healthSnapshot();
            const sent = await this.sendSummary(summary);
            if (sent !== false) {
                this.health.summaries_sent++;
            }
        } catch (_) {
            // sendSummary already reports transport failures through the agent error path.
        }
    }

    recordHealthError(message) {
        this.health.last_error = message;
        this.reportError(message);
    }

    healthSnapshot() {
        return {
            enabled: this.health.enabled,
            last_scan_at: this.health.last_scan_at,
            files_seen: this.health.files_seen,
            files_read: this.health.files_read,
            lines_read: this.health.lines_read,
            summaries_sent: this.health.summaries_sent,
            last_error: this.health.last_error,
            state_file_path: this.health.state_file_path,
        };
    }

    emptySummary() {
        const now = new Date();
        const intervalMs = Math.max(5, this.settings.scanIntervalSeconds) * 1000;

        return {
            server_id: this.config.serverId,
            window_start: new Date(now.getTime() - intervalMs).toISOString(),
            window_end: now.toISOString(),
            files_scanned: 0,
            lines_scanned: 0,
            total_requests: 0,
            status_2xx: 0,
            status_3xx: 0,
            status_4xx: 0,
            status_5xx: 0,
            http_404: 0,
            http_500: 0,
            suspicious_count: 0,
            top_ips: [],
            top_urls: [],
            suspicious_samples: [],
            parser_errors: [],
            _ipCounts: new Map(),
            _urlCounts: new Map(),
        };
    }

    discoverLogFiles(pathsToScan) {
        const found = [];

        for (const inputPath of pathsToScan) {
            const normalized = path.resolve(inputPath);
            if (!fs.existsSync(normalized)) {
                continue;
            }

            const stat = fs.statSync(normalized);
            if (stat.isFile() && normalized.toLowerCase().endsWith('.log')) {
                found.push(normalized);
            } else if (stat.isDirectory()) {
                this.walkDirectory(normalized, found);
            }
        }

        return found.sort();
    }

    walkDirectory(dir, found) {
        for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            const fullPath = path.join(dir, entry.name);
            if (entry.isDirectory()) {
                this.walkDirectory(fullPath, found);
            } else if (entry.isFile() && entry.name.toLowerCase().endsWith('.log')) {
                found.push(fullPath);
            }
        }
    }

    readNewLines(file, maxLines) {
        const stat = fs.statSync(file);
        const previous = this.state.files[file] || {};
        let offset = Number(previous.offset || 0);

        if (stat.size < offset) {
            offset = 0;
        }

        if (stat.size === offset) {
            return { lines: [] };
        }

        const fd = fs.openSync(file, 'r');
        const lines = [];
        const chunkSize = 64 * 1024;
        const buffer = Buffer.alloc(chunkSize);
        let position = offset;
        let carry = '';

        try {
            while (position < stat.size && lines.length < maxLines) {
                const bytesToRead = Math.min(chunkSize, stat.size - position);
                const bytesRead = fs.readSync(fd, buffer, 0, bytesToRead, position);
                if (bytesRead <= 0) {
                    break;
                }

                position += bytesRead;
                const text = carry + buffer.subarray(0, bytesRead).toString('utf8');
                const parts = text.split(/\r?\n/);
                carry = parts.pop() || '';

                for (const part of parts) {
                    if (part) {
                        lines.push(part);
                        if (lines.length >= maxLines) {
                            break;
                        }
                    }
                }
            }

            if (position >= stat.size && carry && lines.length < maxLines) {
                lines.push(carry);
                carry = '';
            }
        } finally {
            fs.closeSync(fd);
        }

        this.state.files[file] = {
            offset: position,
            size: stat.size,
            mtimeMs: stat.mtimeMs,
            updatedAt: new Date().toISOString(),
        };

        return {
            fields: this.fieldsForFile(file),
            lines,
        };
    }

    fieldsForFile(file) {
        const stored = this.state.files[file]?.fields;
        if (Array.isArray(stored) && stored.length > 0) {
            return stored;
        }

        try {
            const sample = fs.readFileSync(file, { encoding: 'utf8', flag: 'r' }).slice(0, 65536);
            const header = sample.split(/\r?\n/).find(line => line.startsWith('#Fields:'));
            if (header) {
                const fields = header.replace('#Fields:', '').trim().split(/\s+/);
                this.state.files[file] = {
                    ...(this.state.files[file] || {}),
                    fields,
                };
                return fields;
            }
        } catch (_) {
            return null;
        }

        return null;
    }

    processLines(lines, summary, settings, file, initialFields) {
        let fields = initialFields;

        for (const line of lines) {
            if (line.startsWith('#Fields:')) {
                fields = line.replace('#Fields:', '').trim().split(/\s+/);
                this.state.files[file] = {
                    ...(this.state.files[file] || {}),
                    fields,
                };
                continue;
            }

            if (line.startsWith('#') || !fields) {
                continue;
            }

            const row = this.parseW3cLine(line, fields);
            if (!row) {
                continue;
            }

            summary.total_requests++;
            const status = Number(row['sc-status'] || 0);
            if (status >= 200 && status < 300) summary.status_2xx++;
            if (status >= 300 && status < 400) summary.status_3xx++;
            if (status >= 400 && status < 500) summary.status_4xx++;
            if (status >= 500 && status < 600) summary.status_5xx++;
            if (status === 404) summary.http_404++;
            if (status === 500) summary.http_500++;

            const ip = row['c-ip'] || 'unknown';
            const url = this.buildUrl(row);
            summary._ipCounts.set(ip, (summary._ipCounts.get(ip) || 0) + 1);
            summary._urlCounts.set(url, (summary._urlCounts.get(url) || 0) + 1);

            const matchedPattern = this.detectSuspicious(row, url);
            if (matchedPattern) {
                summary.suspicious_count++;
                if (summary.suspicious_samples.length < settings.sampleLimit) {
                    summary.suspicious_samples.push({
                        timestamp: this.timestampFor(row),
                        ip,
                        method: row['cs-method'] || null,
                        url,
                        status_code: status || null,
                        matched_pattern: matchedPattern,
                        user_agent: this.decodeIisValue(row['cs(User-Agent)'] || ''),
                        raw: settings.sendRawSamples ? line.slice(0, 4000) : null,
                    });
                }
            }
        }
    }

    parseW3cLine(line, fields) {
        const parts = line.trim().split(/\s+/);
        if (parts.length < fields.length) {
            return null;
        }

        return fields.reduce((row, field, index) => {
            row[field] = parts[index] === '-' ? '' : parts[index];
            return row;
        }, {});
    }

    buildUrl(row) {
        const stem = this.decodeIisValue(row['cs-uri-stem'] || '/');
        const query = row['cs-uri-query'] ? this.decodeIisValue(row['cs-uri-query']) : '';

        return query ? `${stem}?${query}` : stem;
    }

    timestampFor(row) {
        if (row.date && row.time) {
            return `${row.date}T${row.time}Z`;
        }

        return new Date().toISOString();
    }

    decodeIisValue(value) {
        try {
            return decodeURIComponent(String(value).replace(/\+/g, ' '));
        } catch (_) {
            return String(value);
        }
    }

    detectSuspicious(row, url) {
        const userAgent = this.decodeIisValue(row['cs(User-Agent)'] || '');
        const rawUrl = `${row['cs-uri-stem'] || ''}?${row['cs-uri-query'] || ''}`;
        if (this.isAllowlisted(row['c-ip'] || '', url, userAgent)) {
            return null;
        }

        const haystack = `${url} ${rawUrl} ${userAgent}`.toLowerCase();
        const patterns = [
            'union select',
            'information_schema',
            '../',
            '..\\',
            '%2e%2e',
            'cmd.exe',
            'powershell',
            'cfexecute',
            'cfide',
            'base64',
            'oastify.com',
            'burpcollaborator',
            'sqlmap',
            'nikto',
            'acunetix',
            'nessus',
        ];

        for (const pattern of patterns) {
            if (haystack.includes(pattern)) {
                return pattern;
            }
        }

        if (userAgent.toLowerCase().includes('googlebot') && !userAgent.toLowerCase().includes('google.com/bot.html')) {
            return 'suspicious_googlebot';
        }

        return null;
    }

    isAllowlisted(ip, url, userAgent) {
        const allowlist = this.settings.allowlist;
        const normalizedIp = String(ip || '').toLowerCase();
        const normalizedUrl = String(url || '').toLowerCase();
        const normalizedUserAgent = String(userAgent || '').toLowerCase();

        if (allowlist.ipAddresses.some(item => normalizedIp === String(item).trim().toLowerCase())) {
            return true;
        }

        if (allowlist.urlPathContains.some(item => {
            const fragment = String(item).trim().toLowerCase();
            return fragment !== '' && normalizedUrl.includes(fragment);
        })) {
            return true;
        }

        return allowlist.userAgents.some(item => {
            const fragment = String(item).trim().toLowerCase();
            return fragment !== '' && normalizedUserAgent.includes(fragment);
        });
    }

    topValues(counts, limit) {
        return Array.from(counts.entries())
            .sort((a, b) => b[1] - a[1])
            .slice(0, limit)
            .map(([value, count]) => ({ value, count }));
    }
}

class NetworkCheckRunner {
    constructor(config, sendResults, reportError) {
        this.config = config;
        this.sendResults = sendResults;
        this.reportError = reportError;
        this.isRunning = false;
        this.isChecking = false;
        this.timer = null;
    }

    get settings() {
        const source = this.config.networkChecks || {};
        const featureEnabled = Boolean(this.config.featureFlags?.networkChecks);

        return {
            enabled: Boolean(source.enabled || featureEnabled),
            scanIntervalSeconds: Number(source.scanIntervalSeconds || 60),
            timeoutMs: Number(source.timeoutMs || 3000),
            maxChecksPerRun: Number(source.maxChecksPerRun || 50),
            checks: Array.isArray(source.checks) ? source.checks.filter(Boolean) : [],
        };
    }

    start() {
        const settings = this.settings;
        if (!settings.enabled || settings.checks.length === 0) {
            return;
        }

        this.isRunning = true;
        this.schedule(1500);
    }

    stop() {
        this.isRunning = false;
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }

    schedule(delayMs) {
        if (!this.isRunning) {
            return;
        }

        this.timer = setTimeout(async () => {
            await this.runOnce();
            this.schedule(Math.max(10, this.settings.scanIntervalSeconds) * 1000);
        }, delayMs);
    }

    async runOnce() {
        if (this.isChecking) {
            return;
        }

        this.isChecking = true;

        try {
            const checks = this.settings.checks.slice(0, this.settings.maxChecksPerRun);
            const results = [];

            for (const check of checks) {
                if (String(check.type || '') !== 'tcp_port') {
                    continue;
                }

                results.push(await this.checkTcp(check));
            }

            if (results.length > 0) {
                await this.sendResults(results);
            }
        } catch (error) {
            this.reportError(`Network checks failed: ${error.message}`);
        } finally {
            this.isChecking = false;
        }
    }

    checkTcp(check) {
        return new Promise(resolve => {
            const startedAt = Date.now();
            const timeoutMs = Number(check.timeoutMs || this.settings.timeoutMs || 3000);
            const expectedState = check.expectedState || 'open';
            const socket = new net.Socket();
            let settled = false;

            const finish = (isOpen, error = null) => {
                if (settled) {
                    return;
                }

                settled = true;
                socket.destroy();

                const latencyMs = Date.now() - startedAt;
                const expectedOpen = expectedState !== 'closed';
                const isSuccessful = expectedOpen ? isOpen : !isOpen;

                resolve({
                    monitor_id: check.id,
                    status: isSuccessful ? 'up' : (isOpen ? 'unexpected_open' : 'down'),
                    is_successful: isSuccessful,
                    latency_ms: latencyMs,
                    resolved_value: isOpen ? 'open' : 'closed',
                    expected_value: expectedState,
                    error: isSuccessful ? null : (isOpen ? 'Port is open but expected closed.' : (error || 'Connection failed.')),
                    checked_at: new Date().toISOString(),
                });
            };

            socket.setTimeout(timeoutMs);
            socket.once('connect', () => finish(true));
            socket.once('timeout', () => finish(false, 'Connection timed out.'));
            socket.once('error', error => finish(false, error.message));
            socket.connect(Number(check.targetPort), String(check.targetHost));
        });
    }
}

class ServerMonitorAgent {
    constructor() {
        this.config = this.loadConfig();
        this.isRunning = false;
        this.remoteWindowsServices = [];
        this.pendingCommandResults = [];
        this.lastIisLogError = null;
        this.lastNetworkCheckError = null;
        this.iisLogCollector = new IisLogCollector(
            this.config,
            summary => this.sendIisLogSummary(summary),
            message => this.recordIisLogError(message)
        );
        this.networkCheckRunner = new NetworkCheckRunner(
            this.config,
            results => this.sendNetworkCheckResults(results),
            message => this.recordNetworkCheckError(message)
        );
    }

    loadConfig() {
        try {
            const configPath = this.resolveConfigPath();
            const fileConfig = configPath
                ? JSON.parse(fs.readFileSync(configPath, 'utf8'))
                : {};
            const config = {
                ...fileConfig,
                _configPath: configPath,
                serverId: process.env.SERVER_MONITOR_SERVER_ID || fileConfig.serverId,
                apiUrl: process.env.SERVER_MONITOR_API_URL || fileConfig.apiUrl,
                apiKey: process.env.SERVER_MONITOR_API_KEY || fileConfig.apiKey,
                intervalSeconds: Number(process.env.SERVER_MONITOR_INTERVAL_SECONDS || fileConfig.intervalSeconds || 5),
                retryAttempts: Number(process.env.SERVER_MONITOR_RETRY_ATTEMPTS || fileConfig.retryAttempts || 3),
                retryDelayMs: Number(process.env.SERVER_MONITOR_RETRY_DELAY_MS || fileConfig.retryDelayMs || 1000),
                requestTimeoutMs: Number(process.env.SERVER_MONITOR_REQUEST_TIMEOUT_MS || fileConfig.requestTimeoutMs || 10000),
                windowsServices: this.parseWindowsServices(process.env.SERVER_MONITOR_WINDOWS_SERVICES, fileConfig.windowsServices),
                autoDiscoverWindowsServices: this.parseBoolean(process.env.SERVER_MONITOR_AUTO_DISCOVER_WINDOWS_SERVICES, fileConfig.autoDiscoverWindowsServices ?? true),
                windowsServiceDiscoveryPatterns: this.parseWindowsServices(
                    process.env.SERVER_MONITOR_WINDOWS_SERVICE_DISCOVERY_PATTERNS,
                    fileConfig.windowsServiceDiscoveryPatterns || [
                        'coldfusion',
                        'cfusion',
                        'mysql',
                        'mariadb',
                        'mssql',
                        'sql server',
                        'postgres',
                        'redis',
                        'apache',
                        'nginx',
                        'tomcat',
                        'iis',
                        'w3svc',
                        'node',
                        'pm2',
                        'queue',
                        'worker'
                    ]
                ),
                windowsServiceDiscoveryLimit: Number(process.env.SERVER_MONITOR_WINDOWS_SERVICE_DISCOVERY_LIMIT || fileConfig.windowsServiceDiscoveryLimit || 50),
                agentVersion: process.env.SERVER_MONITOR_AGENT_VERSION || fileConfig.agentVersion || packageJson.version,
                featureFlags: fileConfig.featureFlags || {},
                iisLogs: this.parseIisLogsConfig(fileConfig.iisLogs || {}),
                networkChecks: this.parseNetworkChecksConfig(fileConfig.networkChecks || {}, fileConfig.featureFlags || {}),
            };

            if (!config.serverId || !config.apiUrl || !config.apiKey) {
                throw new Error('Missing required config values: serverId, apiUrl, apiKey');
            }

            if (!Number.isFinite(config.intervalSeconds) || config.intervalSeconds < 5) {
                throw new Error('intervalSeconds must be a number greater than or equal to 5');
            }

            if (!Number.isInteger(config.retryAttempts) || config.retryAttempts < 1) {
                throw new Error('retryAttempts must be a positive integer');
            }

            if (!Number.isFinite(config.retryDelayMs) || config.retryDelayMs < 0) {
                throw new Error('retryDelayMs must be a non-negative number');
            }

            if (!Number.isFinite(config.requestTimeoutMs) || config.requestTimeoutMs < 1000) {
                throw new Error('requestTimeoutMs must be at least 1000');
            }

            if (!Number.isFinite(config.windowsServiceDiscoveryLimit) || config.windowsServiceDiscoveryLimit < 1 || config.windowsServiceDiscoveryLimit > 100) {
                throw new Error('windowsServiceDiscoveryLimit must be between 1 and 100');
            }

            if (configPath) {
                console.log(`Loaded config from: ${configPath}`);
            } else {
                console.log('Loaded config from environment variables');
            }

            return config;
        } catch (error) {
            console.error('Failed to load config:', error.message);
            process.exit(1);
        }
    }

    resolveConfigPath() {
        const candidates = [
            process.env.SERVER_MONITOR_CONFIG,
            path.join(path.dirname(process.execPath), 'config.json'),
            path.join(process.cwd(), 'config.json'),
            path.join(__dirname, 'config.json')
        ].filter(Boolean);

        return candidates.find(candidate => fs.existsSync(candidate));
    }

    async collectMetrics() {
        try {
            const [cpu, mem, disk, services] = await Promise.all([
                si.currentLoad(),
                si.mem(),
                si.fsSize(),
                this.collectWindowsServices()
            ]);
            const diskUsage = this.getDiskUsage(disk);
            const hasDiskMetrics = diskUsage && diskUsage.size > 0;

            if (!hasDiskMetrics) {
                console.warn('Unable to determine main disk usage; sending disk metrics as unavailable');
            }

            return {
                server_id: this.config.serverId,
                cpu: Math.round(cpu.currentLoad * 100) / 100,
                ram_used: Math.round((mem.used / (1024 ** 3)) * 100) / 100, // GB
                ram_total: Math.round((mem.total / (1024 ** 3)) * 100) / 100, // GB
                disk_used: hasDiskMetrics ? Math.round((diskUsage.used / (1024 ** 3)) * 100) / 100 : 0, // GB
                disk_total: hasDiskMetrics ? Math.round((diskUsage.size / (1024 ** 3)) * 100) / 100 : 0.01, // GB
                timestamp: new Date().toISOString(),
                agent_version: this.config.agentVersion,
                capabilities: this.agentCapabilities(),
                last_agent_error: [this.lastIisLogError, this.lastNetworkCheckError].filter(Boolean).join(' | ') || null,
                services,
                command_results: this.pendingCommandResults
            };
        } catch (error) {
            console.error('Failed to collect metrics:', error.message);
            throw error;
        }
    }

    async sendMetrics(metrics) {
        const headers = {
            'Content-Type': 'application/json',
            'X-API-Key': this.config.apiKey
        };

        for (let attempt = 1; attempt <= this.config.retryAttempts; attempt++) {
            try {
                const response = await axios.post(this.config.apiUrl, metrics, {
                    headers,
                    timeout: this.config.requestTimeoutMs
                });
                console.log(`Metrics sent successfully (attempt ${attempt})`);
                return response.data;
            } catch (error) {
                const status = error.response ? `HTTP ${error.response.status}` : error.message;
                console.error(`Attempt ${attempt} failed:`, status);

                if (attempt < this.config.retryAttempts) {
                    console.log(`Retrying in ${this.config.retryDelayMs}ms...`);
                    await this.delay(this.config.retryDelayMs);
                } else {
                    throw error;
                }
            }
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    agentCapabilities() {
        const capabilities = ['systemMetrics', 'windowsServices'];

        if (this.config.iisLogs?.enabled) {
            capabilities.push('iisLogs');
        }

        if (this.config.networkChecks?.enabled || this.config.featureFlags?.networkChecks) {
            capabilities.push('networkChecks');
        }

        return capabilities;
    }

    parseWindowsServices(envValue, fileValue) {
        if (envValue) {
            return envValue.split(',').map(service => service.trim()).filter(Boolean);
        }

        return Array.isArray(fileValue) ? fileValue.filter(Boolean) : [];
    }

    parseBoolean(envValue, fileValue) {
        if (envValue !== undefined && envValue !== null && envValue !== '') {
            return ['1', 'true', 'yes', 'on'].includes(String(envValue).toLowerCase());
        }

        return Boolean(fileValue);
    }

    parseIisLogsConfig(fileValue) {
        const paths = this.parseWindowsServices(process.env.SERVER_MONITOR_IIS_LOG_PATHS, fileValue.paths || ['C:/inetpub/logs/LogFiles']);
        const allowlist = fileValue.allowlist || {};

        return {
            enabled: this.parseBoolean(process.env.SERVER_MONITOR_IIS_LOGS_ENABLED, fileValue.enabled ?? false),
            paths,
            scanIntervalSeconds: Number(process.env.SERVER_MONITOR_IIS_LOG_SCAN_INTERVAL_SECONDS || fileValue.scanIntervalSeconds || 60),
            summaryOnly: this.parseBoolean(process.env.SERVER_MONITOR_IIS_LOG_SUMMARY_ONLY, fileValue.summaryOnly ?? true),
            maxLinesPerRun: Number(process.env.SERVER_MONITOR_IIS_LOG_MAX_LINES_PER_RUN || fileValue.maxLinesPerRun || 5000),
            sendRawSamples: this.parseBoolean(process.env.SERVER_MONITOR_IIS_LOG_SEND_RAW_SAMPLES, fileValue.sendRawSamples ?? false),
            sampleLimit: Number(process.env.SERVER_MONITOR_IIS_LOG_SAMPLE_LIMIT || fileValue.sampleLimit || 20),
            statePath: process.env.SERVER_MONITOR_IIS_LOG_STATE_PATH || fileValue.statePath,
            allowlist: {
                ipAddresses: this.parseWindowsServices(process.env.SERVER_MONITOR_IIS_LOG_ALLOWLIST_IPS, allowlist.ipAddresses || allowlist.ip_addresses || []),
                urlPathContains: this.parseWindowsServices(process.env.SERVER_MONITOR_IIS_LOG_ALLOWLIST_URL_CONTAINS, allowlist.urlPathContains || allowlist.url_path_contains || []),
                userAgents: this.parseWindowsServices(process.env.SERVER_MONITOR_IIS_LOG_ALLOWLIST_USER_AGENTS, allowlist.userAgents || allowlist.user_agents || []),
            },
        };
    }

    parseNetworkChecksConfig(fileValue, featureFlags) {
        return {
            enabled: this.parseBoolean(process.env.SERVER_MONITOR_NETWORK_CHECKS_ENABLED, fileValue.enabled ?? featureFlags.networkChecks ?? false),
            scanIntervalSeconds: Number(process.env.SERVER_MONITOR_NETWORK_CHECK_INTERVAL_SECONDS || fileValue.scanIntervalSeconds || 60),
            timeoutMs: Number(process.env.SERVER_MONITOR_NETWORK_CHECK_TIMEOUT_MS || fileValue.timeoutMs || 3000),
            maxChecksPerRun: Number(process.env.SERVER_MONITOR_NETWORK_CHECK_MAX_CHECKS_PER_RUN || fileValue.maxChecksPerRun || 50),
            checks: Array.isArray(fileValue.checks) ? fileValue.checks : [],
        };
    }

    async sendIisLogSummary(summary) {
        const endpoint = this.iisLogSummaryUrl();
        const headers = {
            'Content-Type': 'application/json',
            'X-API-Key': this.config.apiKey
        };

        try {
            await axios.post(endpoint, summary, {
                headers,
                timeout: this.config.requestTimeoutMs
            });
            this.lastIisLogError = null;
            console.log(`IIS log summary sent: ${summary.total_requests} requests, ${summary.suspicious_count} suspicious`);
            return true;
        } catch (error) {
            const status = error.response ? `HTTP ${error.response.status}` : error.message;
            this.recordIisLogError(`IIS log summary send failed: ${status}`);
            return false;
        }
    }

    iisLogSummaryUrl() {
        if (this.config.iisLogs?.apiUrl) {
            return this.config.iisLogs.apiUrl;
        }

        return String(this.config.apiUrl).replace(/\/api\/metrics\/?$/, '/api/iis-logs/summary');
    }

    recordIisLogError(message) {
        this.lastIisLogError = message;
        console.warn(message);
    }

    async sendNetworkCheckResults(results) {
        const endpoint = this.networkCheckResultsUrl();
        const headers = {
            'Content-Type': 'application/json',
            'X-API-Key': this.config.apiKey
        };

        try {
            await axios.post(endpoint, {
                server_id: this.config.serverId,
                results
            }, {
                headers,
                timeout: this.config.requestTimeoutMs
            });
            this.lastNetworkCheckError = null;
            console.log(`Network check results sent: ${results.length}`);
            return true;
        } catch (error) {
            const status = error.response ? `HTTP ${error.response.status}` : error.message;
            this.recordNetworkCheckError(`Network check results send failed: ${status}`);
            return false;
        }
    }

    networkCheckResultsUrl() {
        if (this.config.networkChecks?.apiUrl) {
            return this.config.networkChecks.apiUrl;
        }

        return String(this.config.apiUrl).replace(/\/api\/metrics\/?$/, '/api/network-checks/results');
    }

    recordNetworkCheckError(message) {
        this.lastNetworkCheckError = message;
        console.warn(message);
    }

    async collectWindowsServices() {
        const servicesToCheck = [...new Set([
            ...this.config.windowsServices,
            ...this.remoteWindowsServices
        ])];

        if (process.platform !== 'win32') {
            return [];
        }

        if (this.config.autoDiscoverWindowsServices) {
            return this.collectDiscoveredWindowsServices(servicesToCheck);
        }

        if (servicesToCheck.length === 0) {
            return [];
        }

        return this.collectNamedWindowsServices(servicesToCheck);
    }

    async collectNamedWindowsServices(servicesToCheck) {
        const serviceList = servicesToCheck
            .map(service => service.replace(/'/g, "''"))
            .map(service => `'${service}'`)
            .join(',');
        const command = `$names = @(${serviceList}); Get-Service -Name $names -ErrorAction SilentlyContinue | Select-Object Name, DisplayName, @{Name='Status';Expression={$_.Status.ToString()}}, @{Name='StartType';Expression={$_.StartType.ToString()}} | ConvertTo-Json -Compress`;

        try {
            const stdout = await this.execFile('powershell.exe', [
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                command
            ]);

            if (!stdout.trim()) {
                return [];
            }

            const parsed = JSON.parse(stdout);
            const services = Array.isArray(parsed) ? parsed : [parsed];

            return services.map(service => ({
                name: service.Name,
                display_name: service.DisplayName,
                status: String(service.Status),
                startup_type: service.StartType ? String(service.StartType) : null
            }));
        } catch (error) {
            console.warn(`Failed to collect Windows service status: ${error.message}`);
            return [];
        }
    }

    async collectDiscoveredWindowsServices(explicitServices) {
        const explicit = new Set(explicitServices.map(service => service.toLowerCase()));

        try {
            const stdout = await this.execFile('powershell.exe', [
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                `Get-Service | Select-Object Name, DisplayName, @{Name='Status';Expression={$_.Status.ToString()}}, @{Name='StartType';Expression={$_.StartType.ToString()}} | ConvertTo-Json -Compress`
            ]);

            if (!stdout.trim()) {
                return [];
            }

            const parsed = JSON.parse(stdout);
            const services = Array.isArray(parsed) ? parsed : [parsed];
            const patterns = this.config.windowsServiceDiscoveryPatterns.map(pattern => pattern.toLowerCase());

            return services
                .filter(service => {
                    const name = String(service.Name || '').toLowerCase();
                    const displayName = String(service.DisplayName || '').toLowerCase();

                    return explicit.has(name)
                        || patterns.some(pattern => name.includes(pattern) || displayName.includes(pattern));
                })
                .slice(0, this.config.windowsServiceDiscoveryLimit)
                .map(service => ({
                    name: service.Name,
                    display_name: service.DisplayName,
                    status: String(service.Status),
                    startup_type: service.StartType ? String(service.StartType) : null
                }));
        } catch (error) {
            console.warn(`Failed to auto-discover Windows services: ${error.message}`);
            return this.collectNamedWindowsServices(explicitServices);
        }
    }

    execFile(file, args) {
        return new Promise((resolve, reject) => {
            execFile(file, args, { timeout: this.config.requestTimeoutMs }, (error, stdout, stderr) => {
                if (error) {
                    reject(new Error(stderr || error.message));
                    return;
                }

                resolve(stdout);
            });
        });
    }

    async handleServerResponse(response) {
        if (Array.isArray(response?.monitored_services)) {
            this.remoteWindowsServices = response.monitored_services;
        }

        if (!Array.isArray(response?.commands) || response.commands.length === 0) {
            return;
        }

        for (const command of response.commands) {
            const result = await this.executeServiceCommand(command);
            this.pendingCommandResults.push(result);
        }
    }

    async executeServiceCommand(command) {
        const actionMap = {
            start: 'Start-Service',
            stop: 'Stop-Service',
            restart: 'Restart-Service'
        };
        const psAction = actionMap[command.action];

        if (process.platform !== 'win32' || !psAction) {
            return {
                id: command.id,
                status: 'failed',
                error: 'Unsupported command or platform'
            };
        }

        const serviceName = String(command.service_name || '').replace(/'/g, "''");
        const script = `${psAction} -Name '${serviceName}' -ErrorAction Stop; Start-Sleep -Seconds 1; Get-Service -Name '${serviceName}' | Select-Object Name, Status | ConvertTo-Json -Compress`;

        try {
            const output = await this.execFile('powershell.exe', [
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                script
            ]);

            return {
                id: command.id,
                status: 'succeeded',
                output: output.trim()
            };
        } catch (error) {
            return {
                id: command.id,
                status: 'failed',
                error: error.message
            };
        }
    }

    clearCommandResults() {
        this.pendingCommandResults = [];
    }

    isSystemDisk(disk) {
        const mount = String(disk.mount || '').toLowerCase();
        const fs = String(disk.fs || '').toLowerCase();

        return mount === '/'
            || mount === 'c:'
            || mount === 'c:\\'
            || mount.startsWith('c:')
            || fs === 'c:'
            || fs === 'c:\\'
            || fs.startsWith('c:');
    }

    getDiskUsage(disks) {
        const mainDisk = disks.find(d => this.isSystemDisk(d)) || disks.find(d => d.size > 0);

        if (mainDisk && Number.isFinite(mainDisk.used) && Number.isFinite(mainDisk.size) && mainDisk.size > 0) {
            return {
                used: mainDisk.used,
                size: mainDisk.size
            };
        }

        return this.getDiskUsageFromStatfs();
    }

    getDiskUsageFromStatfs() {
        const rootPath = process.platform === 'win32' ? 'C:\\' : '/';

        try {
            const stats = fs.statfsSync(rootPath);
            const size = Number(stats.blocks) * Number(stats.bsize);
            const free = Number(stats.bfree) * Number(stats.bsize);

            if (!Number.isFinite(size) || !Number.isFinite(free) || size <= 0) {
                return null;
            }

            return {
                used: Math.max(0, size - free),
                size
            };
        } catch (error) {
            console.warn(`Failed to collect disk usage from ${rootPath}: ${error.message}`);
            return null;
        }
    }

    async run() {
        console.log(`Server Monitor Agent started for server: ${this.config.serverId}`);
        console.log(`Sending metrics to: ${this.config.apiUrl}`);
        console.log(`Interval: ${this.config.intervalSeconds} seconds`);

        this.isRunning = true;
        this.iisLogCollector.start();
        this.networkCheckRunner.start();

        while (this.isRunning) {
            try {
                const metrics = await this.collectMetrics();
                console.log('Collected metrics:', {
                    cpu: metrics.cpu + '%',
                    ram: `${metrics.ram_used}GB / ${metrics.ram_total}GB`,
                    disk: `${metrics.disk_used}GB / ${metrics.disk_total}GB`,
                    services: metrics.services.length
                });

                const response = await this.sendMetrics(metrics);
                this.clearCommandResults();
                await this.handleServerResponse(response);
            } catch (error) {
                console.error('Failed to send metrics after all retries:', error.message);
            }

            await this.delay(this.config.intervalSeconds * 1000);
        }
    }

    stop() {
        console.log('Stopping Server Monitor Agent...');
        this.isRunning = false;
        this.iisLogCollector.stop();
        this.networkCheckRunner.stop();
    }
}

// Handle graceful shutdown
process.on('SIGINT', () => {
    console.log('\nReceived SIGINT, shutting down gracefully...');
    if (global.agent) {
        global.agent.stop();
    }
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('\nReceived SIGTERM, shutting down gracefully...');
    if (global.agent) {
        global.agent.stop();
    }
    process.exit(0);
});

// Start the agent
const agent = new ServerMonitorAgent();
global.agent = agent;

agent.run().catch(error => {
    console.error('Agent failed to start:', error.message);
    process.exit(1);
});
