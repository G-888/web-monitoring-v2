<?php

namespace App\Services;

use App\Models\AgentDeploymentAudit;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AgentDeploymentService
{
    public function generatePlainKey(Server $server): string
    {
        $plainKey = 'srv_'.$server->server_id.'_'.Str::random(48);

        $server->forceFill([
            'agent_api_key_hash' => $this->hashKey($plainKey),
            'agent_api_key_rotated_at' => now(),
        ])->save();

        return $plainKey;
    }

    public function keyMatches(Server $server, string $plainKey): bool
    {
        if (! $server->agent_api_key_hash) {
            return false;
        }

        return hash_equals($server->agent_api_key_hash, $this->hashKey($plainKey));
    }

    public function buildConfig(Server $server, string $plainKey, array $options = []): array
    {
        $schemaVersion = $server->config_schema_version ?: config('agent.default_config_schema_version');
        $featureFlags = $options['featureFlags'] ?? config('agent.feature_flags', []);
        $windowsServices = $options['windowsServices'] ?? $this->defaultWindowsServices($server);
        $autoUpdateEnabled = (bool) ($options['autoUpdateEnabled'] ?? config('agent.auto_update.enabled', false));
        $iisLogs = $options['iisLogs'] ?? config('agent.iis_logs', []);

        return [
            'serverId' => $server->server_id,
            'serverName' => $server->name,
            'serverType' => $server->server_type,
            'apiUrl' => url('/api/metrics'),
            'apiKey' => $plainKey,
            'configSchemaVersion' => $schemaVersion,
            'config_schema_version' => $schemaVersion,
            'intervalSeconds' => (int) ($options['intervalSeconds'] ?? 5),
            'featureFlags' => $featureFlags,
            'windowsServices' => array_values($windowsServices),
            'iisLogs' => [
                'enabled' => (bool) ($iisLogs['enabled'] ?? false),
                'paths' => array_values((array) ($iisLogs['paths'] ?? [])),
                'scanIntervalSeconds' => (int) ($iisLogs['scan_interval_seconds'] ?? $iisLogs['scanIntervalSeconds'] ?? 60),
                'summaryOnly' => (bool) ($iisLogs['summary_only'] ?? $iisLogs['summaryOnly'] ?? true),
                'maxLinesPerRun' => (int) ($iisLogs['max_lines_per_run'] ?? $iisLogs['maxLinesPerRun'] ?? 5000),
                'sendRawSamples' => (bool) ($iisLogs['send_raw_samples'] ?? $iisLogs['sendRawSamples'] ?? false),
                'sampleLimit' => (int) ($iisLogs['sample_limit'] ?? $iisLogs['sampleLimit'] ?? 20),
            ],
            'autoDiscoverWindowsServices' => true,
            'pollCommands' => true,
            'autoUpdate' => [
                'enabled' => $autoUpdateEnabled,
                'checkUrl' => config('agent.auto_update.check_url'),
                'downloadUrl' => config('agent.auto_update.download_url'),
            ],
            'retryAttempts' => 3,
            'retryDelayMs' => 1000,
            'requestTimeoutMs' => 10000,
        ];
    }

    public function preview(Server $server): array
    {
        return $this->buildConfig($server, '<generated-on-download>', [
            'windowsServices' => $this->defaultWindowsServices($server),
        ]);
    }

    public function defaultWindowsServices(Server $server): array
    {
        $configured = $server->windowsServices()
            ->where('is_monitored', true)
            ->orderBy('service_name')
            ->pluck('service_name')
            ->all();

        $template = config('agent.server_type_templates.'.($server->server_type ?: ''), []);

        return collect($configured)
            ->merge($template)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function normalizeOptions(Request $request, Server $server): array
    {
        $featureFlags = config('agent.feature_flags', []);

        if ($request->has('featureFlags')) {
            $requestedFlags = (array) $request->input('featureFlags', []);
            $featureFlags = collect($featureFlags)
                ->map(fn ($enabled, string $flag) => filter_var($requestedFlags[$flag] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->all();
        }

        $services = $this->defaultWindowsServices($server);

        if ($request->filled('windowsServices')) {
            $raw = $request->input('windowsServices');
            $services = is_array($raw)
                ? $raw
                : preg_split('/\R|,/', (string) $raw);
        }

        return [
            'featureFlags' => $featureFlags,
            'windowsServices' => collect($services)
                ->map(fn ($service) => trim((string) $service))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'autoUpdateEnabled' => $request->boolean('autoUpdateEnabled', config('agent.auto_update.enabled', false)),
            'intervalSeconds' => (int) $request->input('intervalSeconds', 5),
        ];
    }

    public function createPackage(Server $server, array $config): string
    {
        $packageDir = storage_path('app/agent-packages');
        File::ensureDirectoryExists($packageDir);

        $zipPath = $packageDir.'/'.$this->packageFilename($server);
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Unable to create agent package.');
        }

        $agentPath = base_path('server-monitor-agent/dist/server-monitor-agent.exe');
        if (! File::exists($agentPath)) {
            throw new RuntimeException('server-monitor-agent.exe was not found.');
        }

        $zip->addFile($agentPath, 'server-monitor-agent.exe');
        $zip->addFromString('config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->addFromString('install-service.ps1', $this->packageInstallScript());

        foreach ([
            'uninstall-service.ps1',
            'restart-agent.ps1',
            'update-agent.ps1',
        ] as $script) {
            $path = base_path("server-monitor-agent/installer/{$script}");
            if (File::exists($path)) {
                $zip->addFile($path, $script);
            } else {
                $zip->addFromString($script, "# {$script}\n");
            }
        }

        $zip->addFromString('README.txt', $this->readme($server));
        $zip->addFromString('logs/README.txt', "Agent logs are written here by the Windows service.\n");
        $zip->close();

        return $zipPath;
    }

    private function packageInstallScript(): string
    {
        return <<<'POWERSHELL'
Param(
    [string]$InstallPath = "C:\Program Files\ServerMonitorAgent",
    [string]$ExeName = "server-monitor-agent.exe"
)

function Require-Admin {
    if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
        Write-Error "This script must be run as Administrator."; exit 1
    }
}

Require-Admin

$PackagePath = Split-Path -Path $MyInvocation.MyCommand.Path -Parent
$srcExe = Join-Path -Path $PackagePath -ChildPath ".\server-monitor-agent.exe"
$srcConfig = Join-Path -Path $PackagePath -ChildPath ".\config.json"

if (-not (Test-Path $srcExe)) {
    Write-Error "Packaged executable not found at $srcExe."; exit 1
}

if (-not (Test-Path $srcConfig)) {
    Write-Error "Generated config.json not found at $srcConfig."; exit 1
}

Write-Output "Installing ServerMonitorAgent to $InstallPath"

if (-not (Test-Path $InstallPath)) {
    New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
}

$dstExe = Join-Path $InstallPath $ExeName
$dstConfig = Join-Path $InstallPath "config.json"

Copy-Item -Path $srcExe -Destination $dstExe -Force
Copy-Item -Path $srcConfig -Destination $dstConfig -Force

$logs = Join-Path $InstallPath "logs"
if (-not (Test-Path $logs)) {
    New-Item -ItemType Directory -Path $logs -Force | Out-Null
}

$svcName = "ServerMonitorAgent"
$exists = Get-Service -Name $svcName -ErrorAction SilentlyContinue
if ($exists) {
    Write-Output "Service $svcName already exists. Stopping and updating binary/config."
    Stop-Service -Name $svcName -Force -ErrorAction SilentlyContinue
} else {
    Write-Output "Creating service $svcName"
    sc.exe create $svcName binPath= "`"$dstExe`"" start= auto DisplayName= "Server Monitor Agent" | Out-Null
}

Start-Service -Name $svcName -ErrorAction SilentlyContinue
Write-Output "Service $svcName installed and started."
POWERSHELL;
    }

    public function packageFilename(Server $server): string
    {
        $name = Str::of($server->name)->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('-');
        $version = $server->agent_version ?: config('agent.latest_agent_version') ?: 'new';

        return "ServerMonitorAgent-{$name}-v{$version}.zip";
    }

    public function audit(Server $server, string $action, array $metadata = [], ?Request $request = null): void
    {
        AgentDeploymentAudit::create([
            'server_id' => $server->id,
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
        ]);
    }

    private function hashKey(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    private function readme(Server $server): string
    {
        return implode(PHP_EOL, [
            'Server Monitor Agent Package',
            '',
            "Server: {$server->name}",
            "Server ID: {$server->server_id}",
            '',
            'Install:',
            '1. Extract this ZIP on the target Windows server.',
            '2. Run PowerShell as Administrator.',
            '3. Execute: .\install-service.ps1',
            '',
            'Maintenance:',
            '- Restart agent: .\restart-agent.ps1',
            '- Update agent: .\update-agent.ps1',
            '- Uninstall agent: .\uninstall-service.ps1',
            '',
        ]);
    }
}
