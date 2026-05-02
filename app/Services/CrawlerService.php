<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrawlerService
{
    /**
     * Discover internal links from a given URL
     */
    public function discoverLinks(string $baseUrl, int $limit = 50): array
    {
        $discovered = [];
        $queue = [$baseUrl];
        $processed = [];
        $domain = parse_url($baseUrl, PHP_URL_HOST);

        while (!empty($queue) && count($discovered) < $limit) {
            $url = array_shift($queue);
            if (in_array($url, $processed)) continue;
            
            $processed[] = $url;

            try {
                $response = Http::timeout(5)->get($url);
                if (!$response->successful()) continue;

                $html = $response->body();
                $links = $this->extractLinks($html, $baseUrl);

                foreach ($links as $link) {
                    if ($this->isInternal($link, $domain) && !in_array($link, $discovered)) {
                        $discovered[] = $link;
                        if (!in_array($link, $processed) && count($queue) < 100) {
                            $queue[] = $link;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("Crawling failed for {$url}: " . $e->getMessage());
            }
        }

        return $discovered;
    }

    protected function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];
        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                // Handle relative links
                if (!str_starts_with($href, 'http')) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
                $links[] = $href;
            }
        }
        return array_unique($links);
    }

    protected function isInternal(string $url, string $domain): bool
    {
        return parse_url($url, PHP_URL_HOST) === $domain;
    }
}
