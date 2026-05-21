<?php

return [
    'retention_days' => [
        'metrics' => env('MONITORING_RETENTION_METRICS_DAYS', 90),
        'check_results' => env('MONITORING_RETENTION_CHECK_RESULTS_DAYS', 180),
        'iis_summaries' => env('MONITORING_RETENTION_IIS_SUMMARIES_DAYS', 90),
        'iis_suspicious_events' => env('MONITORING_RETENTION_IIS_SUSPICIOUS_DAYS', 180),
        'network_results' => env('MONITORING_RETENTION_NETWORK_RESULTS_DAYS', 180),
        'database_checks' => env('MONITORING_RETENTION_DATABASE_CHECKS_DAYS', 180),
        'report_files' => env('MONITORING_RETENTION_REPORT_FILES_DAYS', 365),
    ],
];
