<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchEngineSeoScanner
{
    private array $spamPatterns = [
        'casino',
        'viagra',
        'porn',
        'loan',
        'betting',
        'crypto',
        'xxx',
        'free money',
        'earn cash',
        'seo spam',
        'cheap pills',
        'payday',
        'gambling',
        'adult',
    ];

    public function scan(Monitor $monitor): array
    {
        if (! config('services.seo_search.enabled')) {
            return [
                'enabled' => false,
                'findings' => [],
                'detected_patterns' => [],
            ];
        }

        $domain = $this->domainFromUrl($monitor->url);

        if (! $domain) {
            return [
                'enabled' => true,
                'findings' => [],
                'detected_patterns' => ['invalid_monitor_domain'],
            ];
        }

        $queries = $this->queriesForDomain($domain);
        $findings = [];

        foreach (config('services.seo_search.providers', []) as $provider) {
            foreach ($this->providerQueries($provider, $domain, $queries) as $query) {
                foreach ($this->search($provider, $query, $domain) as $result) {
                    $flags = $this->flagsForResult($result, $domain);

                    if ($flags === []) {
                        continue;
                    }

                    $findings[] = [
                        'provider' => $provider,
                        'query' => $query,
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'snippet' => $result['snippet'] ?? '',
                        'flags' => $flags,
                    ];
                }
            }
        }

        return [
            'enabled' => true,
            'findings' => $findings,
            'detected_patterns' => collect($findings)
                ->flatMap(fn ($finding) => $finding['flags'])
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function queriesForDomain(string $domain): array
    {
        $spamTerms = collect($this->spamPatterns)
            ->take(8)
            ->map(fn ($term) => "\"{$term}\"")
            ->implode(' OR ');

        return [
            "site:{$domain}",
            "site:{$domain} ({$spamTerms})",
        ];
    }

    private function providerQueries(string $provider, string $domain, array $queries): array
    {
        return match ($provider) {
            'commoncrawl', 'wayback', 'urlscan', 'crtsh' => [$domain],
            default => $queries,
        };
    }

    private function search(string $provider, string $query, string $domain): array
    {
        try {
            return match ($provider) {
                'bing' => $this->searchBing($query),
                'google' => $this->searchGoogle($query),
                'brave' => $this->searchBrave($query),
                'commoncrawl' => $this->searchCommonCrawl($domain),
                'wayback' => $this->searchWayback($domain),
                'urlscan' => $this->searchUrlscan($domain),
                'crtsh' => $this->searchCertificateTransparency($domain),
                default => [],
            };
        } catch (\Throwable $e) {
            Log::warning('SEO search provider failed', [
                'provider' => $provider,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function searchBing(string $query): array
    {
        $key = config('services.seo_search.bing_key');

        if (! $key) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(8);
        if (!$verifySsl) $request->withoutVerifying();
        
        $response = $request
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $key])
            ->get('https://api.bing.microsoft.com/v7.0/search', [
                'q' => $query,
                'count' => config('services.seo_search.result_limit', 10),
                'responseFilter' => 'Webpages',
                'safeSearch' => 'Off',
            ]);

        if (! $response->successful()) {
            Log::warning('Bing SEO search returned an error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            return [];
        }

        return collect($response->json('webPages.value', []))
            ->map(fn ($item) => [
                'title' => $item['name'] ?? '',
                'url' => $item['url'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ])
            ->all();
    }

    private function searchCommonCrawl(string $domain): array
    {
        if (! config('services.seo_search.commoncrawl_enabled')) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(8);
        if (!$verifySsl) $request->withoutVerifying();

        $collections = $request
            ->get('https://index.commoncrawl.org/collinfo.json')
            ->json();

        if (! is_array($collections)) {
            return [];
        }

        return collect($collections)
            ->take((int) config('services.seo_search.commoncrawl_indexes', 2))
            ->flatMap(function ($collection) use ($domain) {
                $api = $collection['cdx-api'] ?? null;

                if (! $api) {
                    return [];
                }

                $verifySsl = config('services.log_ai.verify_ssl', true);
                $request = Http::timeout(10);
                if (!$verifySsl) $request->withoutVerifying();

                $response = $request->get($api, [
                    'url' => "{$domain}/*",
                    'output' => 'json',
                    'fl' => 'timestamp,url,mime,status,digest',
                    'filter' => 'status:200',
                    'limit' => config('services.seo_search.result_limit', 10),
                ]);

                if (! $response->successful()) {
                    return [];
                }

                return collect(explode("\n", trim($response->body())))
                    ->filter()
                    ->map(fn ($line) => json_decode($line, true))
                    ->filter()
                    ->map(fn ($item) => [
                        'title' => 'Common Crawl indexed URL',
                        'url' => $item['url'] ?? '',
                        'snippet' => 'Indexed in Common Crawl '.$collection['id'].' at '.($item['timestamp'] ?? 'unknown time'),
                    ]);
            })
            ->values()
            ->all();
    }

    private function searchWayback(string $domain): array
    {
        if (! config('services.seo_search.wayback_enabled')) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(10);
        if (!$verifySsl) $request->withoutVerifying();

        $response = $request->get('https://web.archive.org/cdx/search/cdx', [
            'url' => "{$domain}/*",
            'matchType' => 'prefix',
            'output' => 'json',
            'fl' => 'timestamp,original,statuscode,mimetype',
            'filter' => 'statuscode:200',
            'collapse' => 'urlkey',
            'limit' => config('services.seo_search.result_limit', 10),
        ]);

        if (! $response->successful()) {
            return [];
        }

        $rows = $response->json();

        if (! is_array($rows) || count($rows) < 2) {
            return [];
        }

        $headers = array_shift($rows);

        return collect($rows)
            ->map(function ($row) use ($headers) {
                $item = array_combine($headers, $row);

                return [
                    'title' => 'Wayback indexed URL',
                    'url' => $item['original'] ?? '',
                    'snippet' => 'Archived by the Wayback Machine at '.($item['timestamp'] ?? 'unknown time'),
                ];
            })
            ->all();
    }

    private function searchUrlscan(string $domain): array
    {
        if (! config('services.seo_search.urlscan_enabled')) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(8)->acceptJson();
        if (!$verifySsl) $request->withoutVerifying();

        $key = config('services.seo_search.urlscan_key');

        if ($key) {
            $request = $request->withHeaders(['API-Key' => $key]);
        }

        $response = $request->get('https://urlscan.io/api/v1/search/', [
            'q' => "domain:{$domain}",
            'size' => config('services.seo_search.result_limit', 10),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('results', []))
            ->map(fn ($item) => [
                'title' => $item['page']['title'] ?? 'urlscan.io result',
                'url' => $item['page']['url'] ?? $item['task']['url'] ?? '',
                'snippet' => implode(' ', array_filter([
                    'Scanned by urlscan.io',
                    $item['page']['domain'] ?? null,
                    isset($item['verdicts']['overall']['malicious'])
                        ? 'malicious='.($item['verdicts']['overall']['malicious'] ? 'true' : 'false')
                        : null,
                ])),
            ])
            ->all();
    }

    private function searchCertificateTransparency(string $domain): array
    {
        if (! config('services.seo_search.crtsh_enabled')) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(10);
        if (!$verifySsl) $request->withoutVerifying();

        $response = $request->get('https://crt.sh/', [
            'q' => "%.{$domain}",
            'output' => 'json',
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json() ?: [])
            ->pluck('name_value')
            ->flatMap(fn ($name) => explode("\n", (string) $name))
            ->map(fn ($name) => trim(str_replace('*.', '', $name)))
            ->filter()
            ->unique()
            ->take((int) config('services.seo_search.result_limit', 10))
            ->map(fn ($name) => [
                'title' => 'Certificate transparency hostname',
                'url' => "https://{$name}",
                'snippet' => "Certificate transparency log contains hostname {$name}",
            ])
            ->values()
            ->all();
    }

    private function searchGoogle(string $query): array
    {
        $key = config('services.seo_search.google_key');
        $cx = config('services.seo_search.google_cx');

        if (! $key || ! $cx) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(8);
        if (!$verifySsl) $request->withoutVerifying();

        $response = $request
            ->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx' => $cx,
                'q' => $query,
                'num' => min(config('services.seo_search.result_limit', 10), 10),
                'safe' => 'off',
            ]);

        if (! $response->successful()) {
            Log::warning('Google SEO search returned an error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            return [];
        }

        return collect($response->json('items', []))
            ->map(fn ($item) => [
                'title' => $item['title'] ?? '',
                'url' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ])
            ->all();
    }

    private function searchBrave(string $query): array
    {
        $key = config('services.seo_search.brave_key');

        if (! $key) {
            return [];
        }

        $verifySsl = config('services.log_ai.verify_ssl', true);
        $request = Http::timeout(8);
        if (!$verifySsl) $request->withoutVerifying();

        $response = $request
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Subscription-Token' => $key,
            ])
            ->get('https://api.search.brave.com/res/v1/web/search', [
                'q' => $query,
                'count' => config('services.seo_search.result_limit', 10),
                'safesearch' => 'off',
            ]);

        if (! $response->successful()) {
            Log::warning('Brave SEO search returned an error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]);

            return [];
        }

        return collect($response->json('web.results', []))
            ->map(fn ($item) => [
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'snippet' => $item['description'] ?? '',
            ])
            ->all();
    }

    private function flagsForResult(array $result, string $expectedDomain): array
    {
        $text = strtolower(implode(' ', [
            $result['title'] ?? '',
            $result['url'] ?? '',
            $result['snippet'] ?? '',
        ]));

        $flags = [];

        foreach ($this->spamPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                $flags[] = "search_spam:{$pattern}";
            }
        }

        $resultDomain = $this->domainFromUrl($result['url'] ?? '');

        if ($resultDomain && ! $this->domainsMatch($resultDomain, $expectedDomain)) {
            $flags[] = 'search_result_domain_mismatch';
        }

        return array_values(array_unique($flags));
    }

    private function domainFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host && ! str_contains($url, '://')) {
            $host = parse_url("https://{$url}", PHP_URL_HOST);
        }

        if (! $host) {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    private function domainsMatch(string $resultDomain, string $expectedDomain): bool
    {
        return $resultDomain === $expectedDomain || str_ends_with($resultDomain, ".{$expectedDomain}");
    }
}
