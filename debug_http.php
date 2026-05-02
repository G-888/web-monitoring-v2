<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$url = 'http://sppajpm6.treasury.gov.my';
try {
    $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    $r = Http::withHeaders(['User-Agent' => $ua, 'Referer' => 'https://www.google.com/'])->withoutVerifying()->timeout(10)->get($url);
    $html = $r->body();
    
    echo "Status: " . $r->status() . "\n";
    
    // Check for suspicious patterns in the HTML
    preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $scripts);
    echo "Scripts found: " . count($scripts[0]) . "\n";
    
    foreach ($scripts[0] as $s) {
        if (str_contains($s, 'eval') || str_contains($s, 'base64') || str_contains($s, 'unescape')) {
            echo "SUSPICIOUS SCRIPT: " . substr($s, 0, 100) . "...\n";
        }
    }

    // Check for hidden div with Thai content
    if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $html)) {
        echo "THAI CHARACTERS DETECTED!\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
