<?php

return [
    // Latest agent version string for display and update recommendations
    'latest_agent_version' => env('LATEST_AGENT_VERSION', null),

    // Minimum supported agent version; agents older than this are 'unsupported'
    'minimum_supported_agent_version' => env('MINIMUM_SUPPORTED_AGENT_VERSION', null),

    // Default config schema version the installer emits
    'default_config_schema_version' => '1.0.0',

    // Allow first valid agent heartbeat to create a server inventory entry
    'auto_register_servers' => env('AGENT_AUTO_REGISTER_SERVERS', true),

    // Backward compatibility for older agents that still use a shared AGENT_API_KEY.
    // Prefer per-server keys for generated configs and disable this after all agents rotate.
    'global_api_key_enabled' => env('AGENT_GLOBAL_API_KEY_ENABLED', true),

    'ingest_limits' => [
        'metrics_max_bytes' => (int) env('AGENT_METRICS_MAX_BYTES', 262144),
        'iis_log_summary_max_bytes' => (int) env('AGENT_IIS_LOG_SUMMARY_MAX_BYTES', 1048576),
        'network_results_max_bytes' => (int) env('AGENT_NETWORK_RESULTS_MAX_BYTES', 262144),
    ],

    'rate_limits' => [
        'metrics_per_minute' => (int) env('AGENT_METRICS_RATE_LIMIT_PER_MINUTE', 12),
        'iis_log_summaries_per_minute' => (int) env('AGENT_IIS_LOG_SUMMARY_RATE_LIMIT_PER_MINUTE', 20),
        'network_results_per_minute' => (int) env('AGENT_NETWORK_RESULTS_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'auto_update' => [
        'enabled' => false,
        'check_url' => env('AGENT_UPDATE_CHECK_URL'),
        'download_url' => env('AGENT_UPDATE_DOWNLOAD_URL'),
    ],

    'server_type_templates' => [
        'application' => [
            'W3SVC',
            'WAS',
            'IISADMIN',
            'ColdFusion 2023 Application Server',
        ],
        'database' => [
            'MySQL80',
        ],
        'app_database' => [
            'W3SVC',
            'WAS',
            'IISADMIN',
            'ColdFusion 2023 Application Server',
            'MySQL80',
        ],
    ],

    // Default feature flags template
    'feature_flags' => [
        'systemMetrics' => true,
        'windowsServices' => true,
        'databaseCheck' => false,
        'backupMonitoring' => false,
        'scheduledJobs' => false,
        'iisLogs' => false,
        'networkChecks' => false,
        'coldfusionLogs' => false,
        'fileIntegrity' => false,
    ],

    'network_checks' => [
        'enabled' => env('AGENT_NETWORK_CHECKS_ENABLED', false),
        'scan_interval_seconds' => (int) env('AGENT_NETWORK_CHECK_SCAN_INTERVAL_SECONDS', 60),
        'timeout_ms' => (int) env('AGENT_NETWORK_CHECK_TIMEOUT_MS', 3000),
        'max_checks_per_run' => (int) env('AGENT_NETWORK_CHECK_MAX_CHECKS_PER_RUN', 50),
    ],

    'iis_logs' => [
        'enabled' => env('AGENT_IIS_LOGS_ENABLED', false),
        'paths' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('AGENT_IIS_LOG_PATHS', 'C:/inetpub/logs/LogFiles'))
        ))),
        'scan_interval_seconds' => (int) env('AGENT_IIS_LOG_SCAN_INTERVAL_SECONDS', 60),
        'summary_only' => env('AGENT_IIS_LOG_SUMMARY_ONLY', true),
        'max_lines_per_run' => (int) env('AGENT_IIS_LOG_MAX_LINES_PER_RUN', 5000),
        'send_raw_samples' => env('AGENT_IIS_LOG_SEND_RAW_SAMPLES', false),
        'sample_limit' => (int) env('AGENT_IIS_LOG_SAMPLE_LIMIT', 20),
        'allowlist' => [
            'ip_addresses' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_IIS_LOG_ALLOWLIST_IPS', ''))
            ))),
            'url_path_contains' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_IIS_LOG_ALLOWLIST_URL_CONTAINS', ''))
            ))),
            'user_agents' => array_values(array_filter(array_map(
                'trim',
                explode(',', env('AGENT_IIS_LOG_ALLOWLIST_USER_AGENTS', ''))
            ))),
        ],
        'alerts' => [
            'http_500_warning' => (int) env('IIS_LOG_HTTP_500_WARNING_THRESHOLD', 5),
            'http_500_critical' => (int) env('IIS_LOG_HTTP_500_CRITICAL_THRESHOLD', env('IIS_LOG_HTTP_500_SPIKE_THRESHOLD', 10)),
            'http_404_warning' => (int) env('IIS_LOG_HTTP_404_WARNING_THRESHOLD', 25),
            'http_404_critical' => (int) env('IIS_LOG_HTTP_404_CRITICAL_THRESHOLD', env('IIS_LOG_HTTP_404_SPIKE_THRESHOLD', 50)),
            'suspicious_warning' => (int) env('IIS_LOG_SUSPICIOUS_WARNING_THRESHOLD', 3),
            'suspicious_critical' => (int) env('IIS_LOG_SUSPICIOUS_CRITICAL_THRESHOLD', env('IIS_LOG_SUSPICIOUS_SPIKE_THRESHOLD', 5)),
            'cooldown_seconds' => (int) env('IIS_LOG_ALERT_COOLDOWN_SECONDS', 900),
        ],
    ],
];
