<?php

use App\Services\WebshellScannerService;
use App\Models\User;
use App\Models\WebshellScan;
use App\Jobs\RunWebshellScanJob;

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
