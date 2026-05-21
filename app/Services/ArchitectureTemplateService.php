<?php

namespace App\Services;

class ArchitectureTemplateService
{
    public const SINGLE_APP_DB = 'single_server_app_db';
    public const TWO_APP_ONE_DB = 'two_app_one_db';
    public const TWO_APP_THREE_DB = 'two_app_three_db';
    public const APP_INNODB_CLUSTER = 'app_innodb_cluster';
    public const APP_ROUTER_DB_CLUSTER = 'app_mysql_router_db_cluster';
    public const CUSTOM = 'custom';

    public function templates(): array
    {
        return [
            self::SINGLE_APP_DB => [
                'name' => 'Single Server App + DB',
                'description' => 'One Windows server provides both application and database roles.',
                'slots' => ['app_database' => ['label' => 'App + DB server', 'min' => 1, 'roles' => ['application', 'database']]],
            ],
            self::TWO_APP_ONE_DB => [
                'name' => '2 App + 1 DB',
                'description' => 'Two application servers connect to one database server.',
                'slots' => [
                    'application' => ['label' => 'Application servers', 'min' => 2, 'roles' => ['application']],
                    'database' => ['label' => 'Database server', 'min' => 1, 'roles' => ['database']],
                ],
            ],
            self::TWO_APP_THREE_DB => [
                'name' => '2 App + 3 DB',
                'description' => 'Two application servers connect to a three-node database cluster.',
                'slots' => [
                    'application' => ['label' => 'Application servers', 'min' => 2, 'roles' => ['application']],
                    'database' => ['label' => 'Database servers', 'min' => 3, 'roles' => ['database']],
                ],
            ],
            self::APP_INNODB_CLUSTER => [
                'name' => 'App + MySQL InnoDB Cluster',
                'description' => 'Application servers connect directly to MySQL InnoDB Cluster nodes.',
                'slots' => [
                    'application' => ['label' => 'Application servers', 'min' => 1, 'roles' => ['application']],
                    'database' => ['label' => 'InnoDB cluster DB nodes', 'min' => 3, 'roles' => ['database']],
                ],
            ],
            self::APP_ROUTER_DB_CLUSTER => [
                'name' => 'App + MySQL Router + DB Cluster',
                'description' => 'Application servers run local MySQL Router and connect to a database cluster.',
                'slots' => [
                    'application' => ['label' => 'App + MySQL Router servers', 'min' => 1, 'roles' => ['application']],
                    'database' => ['label' => 'Database cluster nodes', 'min' => 3, 'roles' => ['database']],
                ],
                'router_ports' => [6446, 6447],
            ],
            self::CUSTOM => [
                'name' => 'Custom',
                'description' => 'Select roles manually.',
                'slots' => [
                    'web' => ['label' => 'Web servers', 'min' => 0, 'roles' => ['web']],
                    'application' => ['label' => 'Application servers', 'min' => 0, 'roles' => ['application']],
                    'database' => ['label' => 'Database servers', 'min' => 0, 'roles' => ['database']],
                    'worker' => ['label' => 'Worker servers', 'min' => 0, 'roles' => ['worker']],
                    'scheduler' => ['label' => 'Scheduler servers', 'min' => 0, 'roles' => ['scheduler']],
                    'file_storage' => ['label' => 'File storage servers', 'min' => 0, 'roles' => ['file_storage']],
                ],
            ],
        ];
    }

    public function find(string $key): array
    {
        return $this->templates()[$key] ?? $this->templates()[self::CUSTOM];
    }
}
