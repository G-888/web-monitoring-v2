<?php

use App\Models\User;
use App\Services\DnsScannerService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

function fakeAssetScanner(): DnsScannerService
{
    return new class extends DnsScannerService {
        public function scanDns($input): array
        {
            return [[
                'type' => 'A',
                'host' => $input,
                'ip' => '104.21.58.174',
                'geo' => [
                    'status' => 'success',
                    'country' => 'Canada',
                    'city' => 'Toronto',
                    'isp' => 'Cloudflare, Inc.',
                    'org' => 'Cloudflare, Inc.',
                    'as' => 'AS13335 Cloudflare, Inc.',
                    'lat' => 43.6532,
                    'lon' => -79.3832,
                ],
            ]];
        }

        public function discoverSubdomains($domain): array
        {
            return [];
        }

        public function fingerprint($url): array
        {
            return ['server' => 'cloudflare', 'cms' => 'Custom', 'security' => ['hsts' => true, 'csp' => false, 'x_frame' => true]];
        }

        public function auditSsl($domain): array
        {
            return ['issuer' => 'Test CA', 'valid_to' => now()->addYear()->toDateString(), 'algorithm' => 'sha256WithRSAEncryption'];
        }

        public function auditCookies($url): array
        {
            return [];
        }

        public function getSeoIntelligence($url): ?array
        {
            return null;
        }

        public function checkSensitivePaths($url): array
        {
            return ['discovered' => [], 'activity' => []];
        }

        public function scanPorts($ip): array
        {
            return [];
        }

        public function calculateSecurityScore($data): array
        {
            return ['score' => 90, 'grade' => 'A+', 'findings' => ['Content Security Policy (CSP) missing.']];
        }
    };
}

test('asset intelligence marks cloudflare geo as edge location with hidden origin', function () {
    config(['security.outbound_scans.allowed_scan_domains' => ['aset.pkpp.gov.my']]);
    $this->app->instance(DnsScannerService::class, fakeAssetScanner());

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('assets.scan'), ['url' => 'aset.pkpp.gov.my']);

    $response->assertRedirect();

    $result = session('manual_asset_result');

    expect($result['cdn_detected'])->toBeTrue()
        ->and($result['cdn_provider'])->toBe('Cloudflare')
        ->and($result['origin_geo_status'])->toBe('hidden_by_cdn')
        ->and($result['edge_geo']['city'])->toBe('Toronto')
        ->and($result['edge_geo']['country'])->toBe('Canada');
});

test('outbound scan policy blocks private and reserved targets', function (string $target) {
    app(\App\Services\OutboundScanGuard::class)->assertAllowed($target);
})->with([
    'localhost' => ['http://localhost'],
    'loopback' => ['http://127.0.0.1'],
    'link local' => ['http://169.254.10.20'],
    'private 10' => ['http://10.0.0.5'],
    'private 172' => ['http://172.16.0.5'],
    'private 192' => ['http://192.168.1.5'],
])->throws(ValidationException::class);

test('asset intelligence controller blocks localhost scan target', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('assets.scan'), ['url' => 'http://127.0.0.1'])
        ->assertSessionHasErrors('url');
});

test('asset intelligence page explains cdn edge geo instead of origin geo', function () {
    $user = User::factory()->create();

    $result = [
        'dns' => fakeAssetScanner()->scanDns('aset.pkpp.gov.my'),
        'subdomains' => [],
        'domain' => 'aset.pkpp.gov.my',
        'cdn_detected' => true,
        'cdn_provider' => 'Cloudflare',
        'edge_geo' => [
            'country' => 'Canada',
            'city' => 'Toronto',
            'isp' => 'Cloudflare, Inc.',
            'as' => 'AS13335 Cloudflare, Inc.',
            'lat' => 43.6532,
            'lon' => -79.3832,
        ],
        'origin_geo_status' => 'hidden_by_cdn',
        'fingerprint' => ['server' => 'cloudflare', 'cms' => 'Custom', 'security' => []],
        'ssl_audit' => [],
        'cookies' => [],
        'seo_intel' => null,
        'vulnerabilities' => [],
        'activity_log' => [],
        'ports' => [],
        'security_audit' => ['score' => 90, 'grade' => 'A+', 'findings' => []],
        'is_ip' => false,
    ];

    $this->actingAs($user)
        ->withSession(['manual_asset_result' => $result])
        ->get(route('assets.index'))
        ->assertOk()
        ->assertSee('Public Edge Location')
        ->assertSee('Origin Geo')
        ->assertSee('Hidden')
        ->assertSee('CDN/WAF detected');
});

test('sensitive path check flags exposed environment files', function () {
    Http::fake([
        'https://example.test/this-path-not-exist-*' => Http::response('not found', 404),
        'https://example.test/.env' => Http::response("APP_KEY=base64:test\nDB_PASSWORD=secret", 200),
        'https://example.test/.git/config' => Http::response('not found', 404),
        'https://example.test/phpinfo.php' => Http::response('not found', 404),
        'https://example.test/admin' => Http::response('not found', 404),
    ]);

    $result = app(DnsScannerService::class)->checkSensitivePaths('https://example.test');

    expect($result['discovered'])->toHaveCount(1)
        ->and($result['discovered'][0]['path'])->toBe('/.env')
        ->and($result['discovered'][0]['severity'])->toBe('CRITICAL')
        ->and($result['activity'][0]['result'])->toBe('EXPOSED')
        ->and($result['activity'][0]['severity'])->toBe('critical');
});

test('sensitive path check filters generic successful error pages', function () {
    Http::fake([
        'https://example.test/this-path-not-exist-*' => Http::response('generic application shell', 200),
        'https://example.test/.env' => Http::response('generic application shell', 200),
        'https://example.test/.git/config' => Http::response('generic application shell', 200),
        'https://example.test/phpinfo.php' => Http::response('generic application shell', 200),
        'https://example.test/admin' => Http::response('generic application shell', 200),
    ]);

    $result = app(DnsScannerService::class)->checkSensitivePaths('https://example.test');

    expect($result['discovered'])->toBeEmpty()
        ->and(collect($result['activity'])->pluck('result')->unique()->values()->all())->toBe(['Filtered']);
});

test('sensitive path check treats reachable admin as info not critical', function () {
    Http::fake([
        'https://example.test/this-path-not-exist-*' => Http::response('not found', 404),
        'https://example.test/.env' => Http::response('not found', 404),
        'https://example.test/.git/config' => Http::response('not found', 404),
        'https://example.test/phpinfo.php' => Http::response('not found', 404),
        'https://example.test/admin' => Http::response('<html><title>Admin login</title><form>Sign in</form></html>', 200),
    ]);

    $result = app(DnsScannerService::class)->checkSensitivePaths('https://example.test');

    expect($result['discovered'])->toHaveCount(1)
        ->and($result['discovered'][0]['path'])->toBe('/admin')
        ->and($result['discovered'][0]['severity'])->toBe('INFO')
        ->and($result['activity'][3]['result'])->toBe('EXPOSED')
        ->and($result['activity'][3]['severity'])->toBe('info');
});
