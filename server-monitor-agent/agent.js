const si = require('systeminformation');
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');
const packageJson = require('./package.json');

class ServerMonitorAgent {
    constructor() {
        this.config = this.loadConfig();
        this.isRunning = false;
        this.remoteWindowsServices = [];
        this.pendingCommandResults = [];
    }

    loadConfig() {
        try {
            const configPath = this.resolveConfigPath();
            const fileConfig = configPath
                ? JSON.parse(fs.readFileSync(configPath, 'utf8'))
                : {};
            const config = {
                ...fileConfig,
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
