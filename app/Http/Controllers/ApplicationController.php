<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Server;
use App\Models\ApplicationUrl;
use App\Models\ApplicationComponentRule;
use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    public function index(): View
    {
        $apps = Application::with([
            'servers.latestMetric',
            'urls.monitor.latestResult',
            'componentRules',
        ])
            ->orderBy('environment')
            ->orderBy('name')
            ->get();

        return view('applications.index', compact('apps'));
    }

    public function create(): View
    {
        $servers = Server::orderBy('name')->get();
        $roles = Application::ROLES;

        return view('applications.create', compact('servers', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedApplication($request);

        $application = Application::create($data['application']);

        $this->syncUrls($application, $data['urls']);
        $this->syncMappings($application, $data['mappings']);
        $this->syncRules($application, $data['app_servers_min_required'], $data['database_servers_min_required']);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application mapping created successfully.');
    }

    public function edit(Application $application): View
    {
        $application->load(['servers', 'urls', 'componentRules']);

        $servers = Server::orderBy('name')->get();
        $roles = Application::ROLES;

        return view('applications.edit', compact('application', 'servers', 'roles'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $data = $this->validatedApplication($request, $application);

        $application->update($data['application']);
        $this->syncUrls($application, $data['urls']);
        $this->syncMappings($application, $data['mappings']);
        $this->syncRules($application, $data['app_servers_min_required'], $data['database_servers_min_required']);

        return redirect()->route('applications.show', $application)
            ->with('success', 'Application mapping updated successfully.');
    }

    public function show(Application $application)
    {
        $application->load(['servers.latestMetric','urls.monitor.latestResult','componentRules']);

        $summary = $application->healthSummary();
        $monitors = Monitor::query()
            ->orderBy('name')
            ->get(['id', 'name', 'url']);

        return view('applications.show', compact('application', 'summary', 'monitors'));
    }

    private function validatedApplication(Request $request, ?Application $application = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('applications', 'code')->ignore($application?->id),
            ],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'max:255'],
            'urls' => ['nullable'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'mappings.*.role' => ['nullable', Rule::in(Application::ROLES)],
            'mappings.*.is_primary' => ['nullable', 'boolean'],
            'mappings.*.is_required' => ['nullable', 'boolean'],
            'mappings.*.notes' => ['nullable', 'string', 'max:1000'],
            'app_servers_min_required' => ['required', 'integer', 'min:0', 'max:100'],
            'database_servers_min_required' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        return [
            'application' => [
                'name' => $validated['name'],
                'code' => $validated['code'],
                'environment' => $validated['environment'] ?? null,
                'owner_team' => $validated['owner_team'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ],
            'urls' => $this->parseUrls($validated['urls'] ?? null),
            'mappings' => collect($validated['mappings'] ?? [])
                ->filter(fn (array $mapping) => ! empty($mapping['server_id']) && ! empty($mapping['role']))
                ->unique(fn (array $mapping) => $mapping['server_id'].':'.$mapping['role'])
                ->values()
                ->all(),
            'app_servers_min_required' => (int) $validated['app_servers_min_required'],
            'database_servers_min_required' => (int) $validated['database_servers_min_required'],
        ];
    }

    private function parseUrls(mixed $urls): array
    {
        if (is_array($urls)) {
            $urls = implode("\n", $urls);
        }

        return collect(preg_split('/\R/', (string) $urls))
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

        if (($parts['scheme'] ?? null) === null || ($parts['host'] ?? null) === null) {
            return null;
        }

        $normalized = Str::lower($parts['scheme']).'://'.Str::lower($parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $path = $parts['path'] ?? '';
        $path = $path === '/' ? '' : rtrim($path, '/');
        $normalized .= $path;

        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        return $normalized;
    }

    private function syncUrls(Application $application, array $urls): void
    {
        $application->urls()->delete();

        foreach ($urls as $url) {
            $monitor = Monitor::query()
                ->where('url', $url)
                ->first();

            ApplicationUrl::create([
                'application_id' => $application->id,
                'monitor_id' => $monitor?->id,
                'url' => $url,
                'status' => 'unknown',
            ]);
        }
    }

    private function syncMappings(Application $application, array $mappings): void
    {
        $application->servers()->detach();

        foreach ($mappings as $mapping) {
            $application->servers()->attach($mapping['server_id'], [
                'role' => $mapping['role'],
                'is_primary' => ! empty($mapping['is_primary']),
                'is_required' => ! empty($mapping['is_required']),
                'notes' => $mapping['notes'] ?? null,
            ]);
        }
    }

    private function syncRules(Application $application, int $appServersMinRequired, int $databaseServersMinRequired): void
    {
        ApplicationComponentRule::updateOrCreate(
            ['application_id' => $application->id, 'component_type' => Application::RULE_APP_SERVERS],
            ['min_required' => $appServersMinRequired]
        );

        ApplicationComponentRule::updateOrCreate(
            ['application_id' => $application->id, 'component_type' => Application::RULE_DATABASE_SERVERS],
            ['min_required' => $databaseServersMinRequired]
        );

        $application->componentRules()
            ->whereIn('component_type', ['app_server', 'db_server'])
            ->delete();
    }
}
