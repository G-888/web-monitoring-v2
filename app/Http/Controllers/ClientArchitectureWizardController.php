<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationComponentRule;
use App\Models\ApplicationUrl;
use App\Models\Client;
use App\Models\DatabaseMonitor;
use App\Models\Monitor;
use App\Models\NetworkMonitor;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use App\Services\ArchitectureTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientArchitectureWizardController extends Controller
{
    public function create(ArchitectureTemplateService $templates, AgentDeploymentService $deployment)
    {
        $servers = Server::with('applications')->orderBy('group')->orderBy('name')->get();

        return view('clients.architecture-wizard', [
            'clients' => Client::orderBy('name')->get(),
            'servers' => $servers,
            'templates' => $templates->templates(),
            'profiles' => $servers->mapWithKeys(fn (Server $server) => [$server->id => $deployment->profile($server)]),
        ]);
    }

    public function store(Request $request, ArchitectureTemplateService $templates, AgentDeploymentService $deployment)
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'client.name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'client.code' => ['required_without:client_id', 'nullable', 'string', 'max:255', 'unique:clients,code'],
            'client.environment' => ['nullable', 'string', 'max:255'],
            'client.contact_name' => ['nullable', 'string', 'max:255'],
            'client.contact_email' => ['nullable', 'email', 'max:255'],
            'client.support_team' => ['nullable', 'string', 'max:255'],
            'client.status' => ['nullable', 'string', 'max:255'],
            'application.name' => ['required', 'string', 'max:255'],
            'application.code' => ['required', 'string', 'max:255', 'unique:applications,code'],
            'application.environment' => ['nullable', 'string', 'max:255'],
            'application.owner_team' => ['nullable', 'string', 'max:255'],
            'application.description' => ['nullable', 'string', 'max:5000'],
            'application.public_url' => ['nullable', 'string', 'max:2048'],
            'technology_stack' => ['nullable', 'array'],
            'architecture_type' => ['required', Rule::in(array_keys($templates->templates()))],
            'role_servers' => ['nullable', 'array'],
            'role_servers.*' => ['nullable', 'array'],
            'role_servers.*.*' => ['nullable', 'integer', 'exists:servers,id'],
            'new_servers' => ['nullable', 'array'],
            'new_servers.*.slot' => ['nullable', 'string', 'max:255'],
            'new_servers.*.name' => ['nullable', 'string', 'max:255'],
            'new_servers.*.server_id' => ['nullable', 'string', 'max:255', 'unique:servers,server_id'],
            'new_servers.*.ip_address' => ['nullable', 'ip'],
            'new_servers.*.group' => ['nullable', 'string', 'max:255'],
            'generate_packages' => ['nullable', 'boolean'],
        ]);

        $client = $this->resolveClient($validated);
        $template = $templates->find($validated['architecture_type']);
        $selectedServers = $this->selectedServers($validated, $template, $client);

        $application = DB::transaction(function () use ($validated, $client, $template, $selectedServers) {
            $application = Application::create([
                'client_id' => $client->id,
                'name' => $validated['application']['name'],
                'code' => $validated['application']['code'],
                'environment' => $validated['application']['environment'] ?? $client->environment,
                'owner_team' => $validated['application']['owner_team'] ?? $client->support_team,
                'description' => $validated['application']['description'] ?? null,
                'status' => 'active',
                'architecture_type' => $validated['architecture_type'],
                'technology_stack_json' => array_values(array_filter($validated['technology_stack'] ?? [])),
            ]);

            $this->syncUrl($application, $validated['application']['public_url'] ?? null);
            $this->syncRoleMappings($application, $selectedServers, $template);
            $this->syncMinimumRules($application, $selectedServers);
            $this->createNetworkMonitors($application, $selectedServers, $template, $validated['application']['public_url'] ?? null);
            $this->createDatabaseMonitors($application, collect($selectedServers['database'] ?? [])
                ->merge($selectedServers['app_database'] ?? [])
                ->unique('id')
                ->values()
                ->all());

            return $application;
        });

        if ($request->boolean('generate_packages')) {
            return app(ApplicationSetupWizardController::class)
                ->downloadApplicationPackages($request, $application, $deployment);
        }

        return redirect()->route('applications.architecture-review', $application)
            ->with('success', 'Client architecture created successfully.');
    }

    private function resolveClient(array $validated): Client
    {
        if (! empty($validated['client_id'])) {
            return Client::findOrFail($validated['client_id']);
        }

        $data = $validated['client'];

        return Client::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'environment' => $data['environment'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'support_team' => $data['support_team'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    private function selectedServers(array $validated, array $template, Client $client): array
    {
        $selected = [];

        foreach ((array) ($validated['role_servers'] ?? []) as $slot => $serverIds) {
            $selected[$slot] = Server::query()
                ->whereIn('id', collect($serverIds)->filter()->unique()->values())
                ->get()
                ->values()
                ->all();
        }

        foreach ((array) ($validated['new_servers'] ?? []) as $serverData) {
            if (blank($serverData['name'] ?? null) || blank($serverData['server_id'] ?? null)) {
                continue;
            }

            $slot = $serverData['slot'] ?? 'application';
            $selected[$slot] ??= [];
            $selected[$slot][] = Server::create([
                'name' => $serverData['name'],
                'server_id' => $serverData['server_id'],
                'ip_address' => $serverData['ip_address'] ?? null,
                'group' => $serverData['group'] ?? $client->name,
                'is_active' => true,
                'alerts_enabled' => true,
                'offline_threshold_seconds' => 15,
                'alert_cooldown_seconds' => 900,
            ]);
        }

        foreach ($template['slots'] as $slot => $definition) {
            $selected[$slot] ??= [];
        }

        return $selected;
    }

    private function syncUrl(Application $application, ?string $url): void
    {
        $url = $this->normalizeUrl((string) $url);

        if (! $url) {
            return;
        }

        $monitor = Monitor::query()->where('url', $url)->first();
        ApplicationUrl::create([
            'application_id' => $application->id,
            'monitor_id' => $monitor?->id,
            'url' => $url,
            'status' => 'unknown',
        ]);
    }

    private function syncRoleMappings(Application $application, array $selectedServers, array $template): void
    {
        foreach ($template['slots'] as $slot => $definition) {
            foreach (($selectedServers[$slot] ?? []) as $server) {
                foreach ($definition['roles'] as $role) {
                    $application->servers()->attach($server->id, [
                        'role' => $role,
                        'is_primary' => false,
                        'is_required' => true,
                        'notes' => 'Created by client architecture wizard',
                    ]);
                }
            }
        }
    }

    private function syncMinimumRules(Application $application, array $selectedServers): void
    {
        ApplicationComponentRule::updateOrCreate(
            ['application_id' => $application->id, 'component_type' => Application::RULE_APP_SERVERS],
            ['min_required' => max(1, count($selectedServers['application'] ?? []) + count($selectedServers['app_database'] ?? []))]
        );

        ApplicationComponentRule::updateOrCreate(
            ['application_id' => $application->id, 'component_type' => Application::RULE_DATABASE_SERVERS],
            ['min_required' => max(1, count($selectedServers['database'] ?? []) + count($selectedServers['app_database'] ?? []))]
        );
    }

    private function createNetworkMonitors(Application $application, array $selectedServers, array $template, ?string $publicUrl): void
    {
        $appServers = collect($selectedServers['application'] ?? [])
            ->merge($selectedServers['app_database'] ?? [])
            ->values();
        $dbServers = collect($selectedServers['database'] ?? [])
            ->merge($selectedServers['app_database'] ?? [])
            ->values();

        foreach ($appServers as $appServer) {
            foreach ($dbServers as $dbServer) {
                if ($appServer->id === $dbServer->id && count($dbServers) === 1) {
                    continue;
                }

                $this->createTcpMonitor($application, $appServer, $dbServer, $dbServer->ip_address ?: $dbServer->agent_hostname ?: $dbServer->name, 3306, 'app_to_db');
            }

            foreach ($template['router_ports'] ?? [] as $port) {
                $this->createTcpMonitor($application, $appServer, $appServer, '127.0.0.1', $port, 'app_to_router');
            }
        }

        $host = parse_url($this->normalizeUrl((string) $publicUrl) ?: '', PHP_URL_HOST);
        if ($host) {
            NetworkMonitor::create([
                'application_id' => $application->id,
                'name' => "{$application->name} DNS {$host}",
                'type' => NetworkMonitor::TYPE_DNS,
                'protocol' => 'dns',
                'source_type' => NetworkMonitor::SOURCE_CENTRAL,
                'dependency_type' => 'dns',
                'target_host' => $host,
                'dns_record_type' => 'A',
                'expected_value' => null,
                'expected_state' => 'open',
                'timeout_ms' => 3000,
                'interval_seconds' => 300,
                'is_active' => false,
                'alert_cooldown_seconds' => 900,
            ]);
        }
    }

    private function createTcpMonitor(Application $application, Server $source, Server $target, string $host, int $port, string $dependencyType): void
    {
        NetworkMonitor::create([
            'application_id' => $application->id,
            'source_server_id' => $source->id,
            'target_server_id' => $target->id,
            'name' => "{$application->name}: {$source->name} -> {$target->name}:{$port}",
            'type' => NetworkMonitor::TYPE_TCP_PORT,
            'protocol' => 'tcp',
            'source_type' => NetworkMonitor::SOURCE_AGENT,
            'dependency_type' => $dependencyType,
            'target_host' => $host,
            'target_port' => $port,
            'expected_state' => 'open',
            'timeout_ms' => 3000,
            'interval_seconds' => 300,
            'is_active' => false,
            'alert_cooldown_seconds' => 900,
        ]);
    }

    private function createDatabaseMonitors(Application $application, array $databaseServers): void
    {
        foreach ($databaseServers as $server) {
            DatabaseMonitor::create([
                'application_id' => $application->id,
                'server_id' => $server->id,
                'name' => "{$application->name} - {$server->name} MySQL",
                'driver' => 'mysql',
                'db_role' => 'cluster_member',
                'host' => $server->ip_address ?: $server->agent_hostname ?: $server->name,
                'port' => 3306,
                'database_name' => 'mysql',
                'username' => 'configure_me',
                'password' => null,
                'default_query' => 'select 1',
                'is_active' => false,
                'alert_cooldown_seconds' => 900,
            ]);
        }
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = Str::lower($parts['scheme']).'://'.Str::lower($parts['host']);
        $normalized .= isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $normalized .= $path === '/' ? '' : rtrim($path, '/');
        $normalized .= isset($parts['query']) ? '?'.$parts['query'] : '';

        return $normalized;
    }
}
