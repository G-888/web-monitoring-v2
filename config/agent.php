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
        'coldfusionLogs' => false,
        'fileIntegrity' => false,
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
        'alerts' => [
            'http_500_threshold' => (int) env('IIS_LOG_HTTP_500_SPIKE_THRESHOLD', 10),
            'http_404_threshold' => (int) env('IIS_LOG_HTTP_404_SPIKE_THRESHOLD', 50),
            'suspicious_threshold' => (int) env('IIS_LOG_SUSPICIOUS_SPIKE_THRESHOLD', 5),
            'cooldown_seconds' => (int) env('IIS_LOG_ALERT_COOLDOWN_SECONDS', 900),
        ],
    ],
];
