<?php

use App\Models\Monitor;
use App\Services\SearchEngineSeoScanner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

test('search engine seo scanner flags poisoned indexed results', function () {
    config()->set('services.seo_search.enabled', true);
    config()->set('services.seo_search.providers', ['bing']);
    config()->set('services.seo_search.bing_key', 'test-key');
    config()->set('services.seo_search.result_limit', 10);

    Http::fake([
        'api.bing.microsoft.com/*' => Http::response([
            'webPages' => [
                'value' => [
                    [
                        'name' => 'Example casino bonus spam',
                        'url' => 'https://example.com/hidden-spam',
                        'snippet' => 'Casino and betting result indexed under this domain.',
                    ],
                    [
                        'name' => 'Clean page',
                        'url' => 'https://example.com/about',
                        'snippet' => 'Normal company page.',
                    ],
                ],
            ],
        ]),
    ]);

    $monitor = new Monitor([
        'name' => 'Example',
        'url' => 'https://example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $result = app(SearchEngineSeoScanner::class)->scan($monitor);

    expect($result['enabled'])->toBeTrue()
        ->and($result['findings'])->toHaveCount(2)
        ->and($result['detected_patterns'])->toContain('search_spam:casino')
        ->and($result['detected_patterns'])->toContain('search_spam:betting');
});

test('scanner supports free public index providers', function () {
    config()->set('services.seo_search.enabled', true);
    config()->set('services.seo_search.providers', ['commoncrawl', 'wayback', 'urlscan', 'crtsh']);
    config()->set('services.seo_search.result_limit', 10);
    config()->set('services.seo_search.commoncrawl_enabled', true);
    config()->set('services.seo_search.commoncrawl_indexes', 1);
    config()->set('services.seo_search.wayback_enabled', true);
    config()->set('services.seo_search.urlscan_enabled', true);
    config()->set('services.seo_search.crtsh_enabled', true);

    Http::fake([
        'index.commoncrawl.org/collinfo.json' => Http::response([
            ['id' => 'CC-MAIN-TEST', 'cdx-api' => 'https://index.commoncrawl.org/CC-MAIN-TEST-index'],
        ]),
        'index.commoncrawl.org/CC-MAIN-TEST-index*' => Http::response(
            "{\"timestamp\":\"20260101000000\",\"url\":\"https://example.com/casino-page\",\"mime\":\"text/html\",\"status\":\"200\"}\n"
        ),
        'web.archive.org/cdx/search/cdx*' => Http::response([
            ['timestamp', 'original', 'statuscode', 'mimetype'],
            ['20260101000000', 'https://example.com/viagra', '200', 'text/html'],
        ]),
        'urlscan.io/api/v1/search/*' => Http::response([
            'results' => [
                [
                    'page' => [
                        'title' => 'Betting spam',
                        'url' => 'https://example.com/betting',
                        'domain' => 'example.com',
                    ],
                ],
            ],
        ]),
        'crt.sh/*' => Http::response([
            ['name_value' => "casino.example.com\nwww.example.com"],
        ]),
    ]);

    $monitor = new Monitor([
        'name' => 'Example',
        'url' => 'https://example.com',
        'interval' => 60,
        'is_active' => true,
        'seo_enabled' => true,
    ]);

    $result = app(SearchEngineSeoScanner::class)->scan($monitor);

    expect($result['findings'])->toHaveCount(4)
        ->and($result['detected_patterns'])->toContain('search_spam:casino')
        ->and($result['detected_patterns'])->toContain('search_spam:viagra')
        ->and($result['detected_patterns'])->toContain('search_spam:betting');
});
