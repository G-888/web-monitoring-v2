<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoScannerService
{
    public function __construct(private SearchEngineSeoScanner $searchEngineSeoScanner)
    {
    }

    protected array $userAgents = [
        'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'mobile' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
    ];

    protected array $spamKeywords = [
        'viagra', 'cialis', 'levitra', 'casino', 'gambling', 'poker', 'slots', 'betting', 'weight loss', 'cheap pharmacy',
        'slot', 'pg slot', 'ฝากถอน', 'ไม่มีขั้นต่ำ', 'เว็บตรง', 'สล็อต'
    ];

    /**
     * Perform a comprehensive SEO scan of a URL
     */
    public function scan(string $url): array
    {
        $results = [];
        $findings = [];
        $hashes = [];

        foreach ($this->userAgents as $key => $ua) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => $ua,
                    'Referer' => 'https://www.google.com/'
                ])
                ->timeout(10)
                ->get($url);

                $content = $response->body();
                if ($content) {
                    $hashes[$key] = hash('sha256', $content);
                    $results[$key] = $content;

                    // 1. Language Anomaly Detection (e.g. Thai chars on .gov.my)
                    if (str_contains($url, '.gov.my') && preg_match('/[\x{0E00}-\x{0E7F}]/u', $content)) {
                        $findings[] = "FOREIGN_LANGUAGE_ANOMALY: Thai characters detected on government domain";
                    }

                    // Inspection logic for the desktop version (primary)
                    if ($key === 'desktop') {
                        $findings = array_merge($findings, $this->inspectContent($content));
                    }
                }
            } catch (\Exception $e) {
                Log::error("SEO Scan failed for UA {$key}: " . $e->getMessage());
            }
        }

        // Cloak Detection
        $cloakingDetected = false;
        if (count($hashes) > 1) {
            $baseHash = $hashes['desktop'] ?? null;
            foreach ($hashes as $key => $hash) {
                if ($key !== 'desktop' && $hash !== $baseHash) {
                    $cloakingDetected = true;
                    break;
                }
            }
        }

        $searchResult = $this->scanSearchIndex($url);
        foreach ($searchResult['findings'] ?? [] as $finding) {
            $flags = implode(', ', $finding['flags'] ?? []);
            $title = $finding['title'] ?? 'Indexed result';
            $resultUrl = $finding['url'] ?? '';

            $findings[] = trim("SEARCH_INDEX_POISONING: {$title} {$resultUrl} [{$flags}]");
        }

        return [
            'status' => ($cloakingDetected || !empty($findings)) ? 'suspicious' : 'clean',
            'cloaking' => $cloakingDetected,
            'findings' => array_values(array_unique($findings)),
            'hashes' => $hashes,
            'search_enabled' => $searchResult['enabled'] ?? false,
            'search_findings' => $searchResult['findings'] ?? [],
            'search_queries' => $searchResult['queries'] ?? [],
            'search_detected_patterns' => $searchResult['detected_patterns'] ?? [],
        ];
    }

    private function scanSearchIndex(string $url): array
    {
        try {
            return $this->searchEngineSeoScanner->scan(new Monitor([
                'name' => $url,
                'url' => $url,
                'interval' => 60,
                'is_active' => true,
                'seo_enabled' => true,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Search-index SEO scan failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'enabled' => config('services.seo_search.enabled', false),
                'findings' => [],
                'detected_patterns' => ['search_index_scan_failed'],
                'queries' => [],
            ];
        }
    }

    /**
     * Inspect content for injections and spam
     */
    protected function inspectContent(string $content): array
    {
        $findings = [];

        // 1. Keyword Scanning
        foreach ($this->spamKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $findings[] = "SPAM_KEYWORD: {$keyword}";
            }
        }

        // 2. Script Injections
        if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $content, $matches)) {
            foreach ($matches[1] as $script) {
                if (str_contains($script, 'eval(') || str_contains($script, 'base64')) {
                    $findings[] = "SUSPICIOUS_SCRIPT: eval/base64 detected";
                }
            }
        }

        // 3. Hidden IFrames
        if (preg_match('/<iframe\b[^>]*style=["\'][^"\']*(display:\s*none|visibility:\s*hidden|width:\s*0|height:\s*0)[^"\']*["\']/i', $content)) {
            $findings[] = "HIDDEN_IFRAME_DETECTED";
        }

        // 4. Base64 Payloads (Generic check)
        if (preg_match('/data:text\/html;base64,/', $content)) {
            $findings[] = "BASE64_HTML_PAYLOAD";
        }

        return array_unique($findings);
    }
}
