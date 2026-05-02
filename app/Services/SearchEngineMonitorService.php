<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchEngineMonitorService
{
    /**
     * Scrape DuckDuckGo for indexed pages of a domain
     */
    public function getIndexedPages(string $domain): array
    {
        $url = "https://duckduckgo.com/html/?q=site:{$domain}";
        
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->get($url);

            if (!$response->successful()) {
                throw new \Exception("DuckDuckGo request failed with status: " . $response->status());
            }

            $html = $response->body();
            return $this->parseResults($html);

        } catch (\Exception $e) {
            Log::error("Search monitoring failed for {$domain}: " . $e->getMessage());
            return [];
        }
    }

    protected function parseResults(string $html): array
    {
        $urls = [];
        // Look for result links in DuckDuckGo HTML structure
        // Usually <a class="result__url" href="...">
        if (preg_match_all('/class="result__url"[^>]*href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $urls[] = urldecode($url);
            }
        }

        return array_unique($urls);
    }
}
