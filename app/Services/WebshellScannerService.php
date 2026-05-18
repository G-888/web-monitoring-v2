<?php

namespace App\Services;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class WebshellScannerService
{
    private const SCANNABLE_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'inc', 'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'txt',
    ];

    private const SKIPPED_DIRECTORIES = [
        '.git', 'node_modules', 'vendor', 'storage/framework', 'storage/app/public/build',
    ];

    private const SIGNATURES = [
        [
            'name' => 'php_code_execution',
            'severity' => 'critical',
            'regex' => '/\b(eval|assert)\s*\(/i',
            'reason' => 'Dynamic PHP code execution is a common webshell primitive.',
        ],
        [
            'name' => 'os_command_execution',
            'severity' => 'critical',
            'regex' => '/\b(shell_exec|passthru|system|proc_open|popen|pcntl_exec)\s*\(/i',
            'reason' => 'Operating-system command execution in web-accessible scripts is high risk.',
        ],
        [
            'name' => 'obfuscated_payload_decoder',
            'severity' => 'high',
            'regex' => '/\b(base64_decode|gzinflate|gzuncompress|str_rot13|hex2bin)\s*\(/i',
            'reason' => 'Payload decoding and string transforms are frequently used to hide webshell code.',
        ],
        [
            'name' => 'request_driven_execution',
            'severity' => 'high',
            'regex' => '/\b(eval|assert|system|shell_exec|passthru|exec)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i',
            'reason' => 'User-controlled request data appears to feed code or command execution.',
        ],
        [
            'name' => 'file_upload_dropper',
            'severity' => 'medium',
            'regex' => '/\b(move_uploaded_file|file_put_contents|fwrite)\s*\(/i',
            'reason' => 'File-write behavior in public scripts can be used as a dropper.',
        ],
        [
            'name' => 'preg_replace_eval_modifier',
            'severity' => 'high',
            'regex' => '/preg_replace\s*\(\s*[\'"][^\'"]*\/e[imsxuADSUXJ]*[\'"]/i',
            'reason' => 'The deprecated preg_replace /e modifier evaluates replacement code.',
        ],
        [
            'name' => 'long_encoded_blob',
            'severity' => 'medium',
            'regex' => '/[A-Za-z0-9+\/]{220,}={0,2}/',
            'reason' => 'Large encoded blobs can indicate packed payloads.',
        ],
    ];

    public function scan(?string $path = null): array
    {
        $target = $this->resolveTargetPath($path);
        $findings = [];
        $scannedFiles = 0;

        foreach ($this->iterFiles($target) as $file) {
            if (!$this->shouldScanFile($file)) {
                continue;
            }

            $scannedFiles++;
            $contents = @file_get_contents($file);
            if ($contents === false || str_contains(substr($contents, 0, 512), "\0")) {
                continue;
            }

            $lines = preg_split('/\R/', $contents) ?: [];
            foreach ($lines as $index => $line) {
                foreach (self::SIGNATURES as $signature) {
                    if (preg_match($signature['regex'], $line)) {
                        $findings[] = [
                            'severity' => $signature['severity'],
                            'signature' => $signature['name'],
                            'file' => $file,
                            'line' => $index + 1,
                            'excerpt' => Str::limit(trim($line), 220),
                            'reason' => $signature['reason'],
                        ];

                        if (count($findings) >= config('services.webshell.max_findings', 200)) {
                            break 3;
                        }
                    }
                }
            }
        }

        return [
            'status' => count($findings) > 0 ? 'suspicious' : 'clean',
            'target' => $target,
            'scanned_files' => $scannedFiles,
            'findings' => $findings,
            'scanned_at' => now()->toDateTimeString(),
        ];
    }

    private function resolveTargetPath(?string $path): string
    {
        $allowedBases = collect(explode(',', (string) config('services.webshell.allowed_paths', public_path())))
            ->map(fn ($base) => realpath(trim($base)))
            ->filter()
            ->values();

        if ($allowedBases->isEmpty()) {
            throw new \InvalidArgumentException('No webshell scan paths are configured.');
        }

        $target = $path ? realpath($path) : $allowedBases->first();
        if (!$target) {
            throw new \InvalidArgumentException('The requested scan path could not be resolved.');
        }

        $authorized = $allowedBases->contains(fn ($base) => $target === $base || str_starts_with($target, $base . DIRECTORY_SEPARATOR));
        if (!$authorized) {
            throw new \InvalidArgumentException('The requested scan path is outside the allowed webshell scan paths.');
        }

        return $target;
    }

    private function iterFiles(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }

    private function shouldScanFile(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        foreach (self::SKIPPED_DIRECTORIES as $directory) {
            if (str_contains($normalized, '/' . $directory . '/')) {
                return false;
            }
        }

        if (filesize($file) > config('services.webshell.max_file_size', 1048576)) {
            return false;
        }

        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::SCANNABLE_EXTENSIONS, true);
    }
}
