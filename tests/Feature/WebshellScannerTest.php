<?php

use App\Services\WebshellScannerService;
use App\Models\User;
use App\Models\Monitor;
use App\Models\SeoScan;
use App\Models\WebshellScan;
use App\Jobs\RunWebshellScanJob;
use App\Jobs\SeoScanJob;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->scanRoot = storage_path('app/testing-webshell');

    if (! is_dir($this->scanRoot)) {
        mkdir($this->scanRoot, 0777, true);
    }

    foreach (glob($this->scanRoot . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    config([
        'services.webshell.allowed_paths' => $this->scanRoot,
        'services.webshell.max_file_size' => 1048576,
        'services.webshell.max_findings' => 200,
    ]);
});

test('webshell scanner flags suspicious php execution patterns', function () {
    file_put_contents($this->scanRoot . DIRECTORY_SEPARATOR . 'shell.php', '<?php system($_GET["cmd"]);');

    $result = app(WebshellScannerService::class)->scan($this->scanRoot);

    expect($result)
        ->status->toBe('suspicious')
        ->scanned_files->toBe(1)
        ->and($result['findings'])->not->toBeEmpty()
        ->and($result['findings'][0]['severity'])->toBe('critical');
});

test('webshell scanner reports clean files', function () {
    file_put_contents($this->scanRoot . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "hello";');

    $result = app(WebshellScannerService::class)->scan($this->scanRoot);

    expect($result)
        ->status->toBe('clean')
        ->scanned_files->toBe(1)
        ->and($result['findings'])->toBe([]);
});

test('webshell scanner blocks paths outside allowed roots', function () {
    app(WebshellScannerService::class)->scan(base_path());
})->throws(\InvalidArgumentException::class);

test('seo security page renders separate seo and webshell tabs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('seo-security.index', ['tab' => 'seo']))
        ->assertOk()
        ->assertSee('URL / SEO Scan')
        ->assertSee('Forensic URL Check')
        ->assertDontSee('Scan allowed local web paths');

    $this->actingAs($user)
        ->get(route('seo-security.index', ['tab' => 'webshell']))
        ->assertOk()
        ->assertSee('Webshell Detection')
        ->assertSee('Scan allowed local web paths')
        ->assertDontSee('Forensic URL Check');
});

test('seo security page renders manual scan results without captured debug payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'manual_url' => 'https://example.test',
            'manual_scan_result' => [
                'status' => 'clean',
                'findings' => [],
            ],
        ])
        ->get(route('seo-security.index', ['tab' => 'seo']))
        ->assertOk()
        ->assertSee('Forensic Analysis Report')
        ->assertSee('No response source captured for this scan.');
});

test('webshell scan redirects back to the webshell tab', function () {
    $this->withoutMiddleware();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('seo-security.webshell-scan'), ['path' => $this->scanRoot])
        ->assertRedirect(route('seo-security.index', ['tab' => 'webshell']));

    expect(WebshellScan::query()->count())->toBe(1);
});

test('scheduled webshell scan job stores scan history', function () {
    file_put_contents($this->scanRoot . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "hello";');

    (new RunWebshellScanJob($this->scanRoot, 'scheduled'))->handle(app(WebshellScannerService::class));

    $scan = WebshellScan::latest('id')->first();

    expect($scan)
        ->not->toBeNull()
        ->source->toBe('scheduled')
        ->status->toBe('clean')
        ->scanned_files->toBe(1)
        ->and($scan->findings)->toBe([]);
});

test('webshell scan history renders on webshell tab', function () {
    $user = User::factory()->create();

    WebshellScan::create([
        'source' => 'scheduled',
        'status' => 'suspicious',
        'target' => $this->scanRoot,
        'scanned_files' => 1,
        'findings' => [['severity' => 'critical']],
        'scanned_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('seo-security.index', ['tab' => 'webshell']))
        ->assertOk()
        ->assertSee('Webshell Scan History')
        ->assertSee('scheduled')
        ->assertSee('suspicious');
});

test('seo scan all queues active monitor urls only', function () {
    Bus::fake();
    config(['security.outbound_scans.allowed_scan_domains' => ['active-one.test', 'active-two.test']]);

    $user = User::factory()->create();
    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Active One',
        'url' => 'https://active-one.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);
    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Active Two',
        'url' => 'https://active-two.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);
    Monitor::create([
        'user_id' => $user->id,
        'name' => 'Inactive',
        'url' => 'https://inactive.test',
        'interval' => 60,
        'is_active' => false,
        'seo_enabled' => true,
    ]);

    $this->actingAs($user)
        ->post(route('seo-security.scan-all'))
        ->assertRedirect(route('seo-security.index', ['tab' => 'seo']));

    Bus::assertDispatched(SeoScanJob::class, 2);
});

test('seo baseline shows latest audit for all active nodes', function () {
    $user = User::factory()->create();
    $target = Monitor::create([
        'user_id' => $user->id,
        'name' => 'Older Audited Node',
        'url' => 'https://older-audited.test',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    SeoScan::create([
        'monitor_id' => $target->id,
        'url' => $target->url,
        'status' => 'clean',
        'findings' => [],
        'diffs' => [],
        'scanned_at' => now()->subDays(2),
    ]);

    for ($i = 1; $i <= 21; $i++) {
        $monitor = Monitor::create([
            'user_id' => $user->id,
            'name' => 'Recent Node '.$i,
            'url' => 'https://recent-'.$i.'.test',
            'interval' => 60,
            'is_active' => true,
            'seo_enabled' => true,
        ]);

        SeoScan::create([
            'monitor_id' => $monitor->id,
            'url' => $monitor->url,
            'status' => 'suspicious',
            'findings' => ['Injected spam'],
            'diffs' => [],
            'scanned_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($user)
        ->get(route('seo-security.index', ['tab' => 'seo']))
        ->assertOk()
        ->assertSee('Older Audited Node')
        ->assertSee('CLEAN')
        ->assertSee('Scan All');
});
