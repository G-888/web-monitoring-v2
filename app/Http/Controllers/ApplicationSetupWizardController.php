<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationComponentRule;
use App\Models\ApplicationUrl;
use App\Models\Monitor;
use App\Models\Server;
use App\Services\AgentDeploymentService;
use App\Services\AgentProfileResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

class ApplicationSetupWizardController extends Controller
{
    public function create(AgentDeploymentService $deployment)
    {
        $servers = Server::with('applications')
            ->orderBy('group')
            ->orderBy('name')
            ->get();
        $profiles = $servers->mapWithKeys(fn (Server $server) => [$server->id => $deployment->profile($server)]);

        return view('applications.setup', [
            'servers' => $servers,
            'roles' => Application::ROLES,
            'deploymentTypes' => ['single-server', 'cluster', 'custom'],
            'deploymentProfiles' => AgentProfileResolver::deploymentProfiles(),
            'profiles' => $profiles,
        ]);
    }

    public function store(Request $request, AgentDeploymentService $deployment)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:applications,code'],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'deployment_type' => ['required', Rule::in(['single-server', 'cluster', 'custom'])],
            'urls' => ['nullable', 'string', 'max:5000'],
            'server_roles' => ['nullable', 'array'],
            'server_roles.*' => ['nullable', 'array'],
            'server_roles.*.*' => ['nullable', Rule::in(Application::ROLES)],
            'app_servers_min_required' => ['nullable', 'integer', 'min:0', 'max:100'],
            'database_servers_min_required' => ['nullable', 'integer', 'min:0', 'max:100'],
            'generate_packages' => ['nullable', 'boolean'],
        ]);

        $application = Application::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'environment' => $validated['environment'] ?? null,
            'owner_team' => $validated['owner_team'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => 'active',
        ]);

        $this->syncUrls($application, $validated['urls'] ?? '');
        $this->syncMappings($application, (array) ($validated['server_roles'] ?? []));
        $this->syncRules(
            $application,
            (int) ($validated['app_servers_min_required'] ?? 1),
            (int) ($validated['database_servers_min_required'] ?? 1)
        );

        if ($request->boolean('generate_packages')) {
            return $this->downloadApplicationPackages($request, $application, $deployment);
        }

        return redirect()
            ->route('applications.show', $application)
            ->with('success', 'Application setup completed.');
    }

    public function downloadApplicationPackages(Request $request, Application $application, AgentDeploymentService $deployment)
    {
        $application = $this->resolveApplication($request, $application);

        $serverIds = DB::table('application_servers')
            ->where('application_id', $application->id)
            ->distinct()
            ->pluck('server_id');
        $servers = Server::query()
            ->whereIn('id', $serverIds)
            ->orderBy('name')
            ->get();

        if ($servers->isEmpty()) {
            $servers = $application->servers()->get()->unique('id')->values();
        }

        abort_if($servers->isEmpty(), 422, 'No servers are mapped to this application.');

        $bundleDir = storage_path('app/agent-packages');
        File::ensureDirectoryExists($bundleDir);

        $bundleName = 'ServerMonitorAgent-'.$this->safeName($application->code ?: $application->name).'-packages.zip';
        $bundlePath = $bundleDir.'/'.$bundleName;

        if (File::exists($bundlePath)) {
            File::delete($bundlePath);
        }

        $bundle = new ZipArchive();
        abort_unless($bundle->open($bundlePath, ZipArchive::CREATE) === true, 500, 'Unable to create application package bundle.');

        $generatedPaths = [];

        foreach ($servers as $server) {
            $plainKey = $deployment->generatePlainKey($server);
            $config = $deployment->buildConfig($server, $plainKey);
            $packagePath = $deployment->createPackage($server, $config);
            $packageName = $deployment->packageFilename($server);

            $bundle->addFile($packagePath, $packageName);
            $generatedPaths[] = $packagePath;

            $deployment->audit($server, 'package_downloaded', [
                'filename' => $packageName,
                'application_id' => $application->id,
                'application' => $application->name,
                'bulk_application_package' => true,
                'profile' => $config['deploymentProfile'] ?? null,
                'feature_flags' => $config['featureFlags'],
                'windows_services' => $config['windowsServices'],
                'manual_override' => false,
            ], $request);
        }

        $bundle->close();

        foreach ($generatedPaths as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        return response()->download($bundlePath, $bundleName)->deleteFileAfterSend(true);
    }

    private function resolveApplication(Request $request, Application $application): Application
    {
        if ($application->exists) {
            return $application;
        }

        $routeApplication = $request->route('application');

        if ($routeApplication instanceof Application) {
            return $routeApplication;
        }

        return Application::query()->findOrFail($routeApplication);
    }

    private function syncUrls(Application $application, string $urls): void
    {
        collect(preg_split('/\R/', $urls))
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->filter()
            ->unique()
            ->each(function (string $url) use ($application) {
                $monitor = Monitor::query()->where('url', $url)->first();

                ApplicationUrl::create([
                    'application_id' => $application->id,
                    'monitor_id' => $monitor?->id,
                    'url' => $url,
                    'status' => 'unknown',
                ]);
            });
    }

    private function syncMappings(Application $application, array $serverRoles): void
    {
        foreach ($serverRoles as $serverId => $roles) {
            $server = Server::find($serverId);

            if (! $server) {
                continue;
            }

            collect($roles)
                ->filter(fn ($role) => in_array($role, Application::ROLES, true))
                ->unique()
                ->each(fn (string $role) => $application->servers()->attach($server->id, [
                    'role' => $role,
                    'is_primary' => false,
                    'is_required' => true,
                    'notes' => null,
                ]));
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

    private function safeName(string $name): string
    {
        return (string) Str::of($name)->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('-');
    }
}
