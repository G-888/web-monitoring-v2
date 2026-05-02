<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$url = 'https://sppakpkm1.treasury.gov.my/';
try {
    $ua = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    $r = Http::withHeaders(['User-Agent' => $ua])->withoutVerifying()->timeout(10)->get($url);
    echo "Status: " . $r->status() . "\n";
    echo "Body length: " . strlen($r->body()) . "\n";
    echo "Body snippet: " . substr(strip_tags($r->body()), 0, 500) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
