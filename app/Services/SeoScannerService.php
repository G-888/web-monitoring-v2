<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoScannerService
{
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
                $response = Http::withHeaders(['User-Agent' => $ua])
                    ->timeout(10)
                    ->get($url);

                $content = $response->body();
                if ($content) {
                    $hashes[$key] = hash('sha256', $content);
                    $results[$key] = $content;

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

        return [
            'status' => ($cloakingDetected || !empty($findings)) ? 'suspicious' : 'clean',
            'cloaking' => $cloakingDetected,
            'findings' => $findings,
            'hashes' => $hashes
        ];
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
