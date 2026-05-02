<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class RipgrepScanner
{
    /**
     * Executes ripgrep search in a specified directory.
     *
     * @param string $pattern
     * @param string $directory
     * @return array
     */
    public function scan(string $pattern, string $directory): array
    {
        $allowedBases = array_filter(array_map('realpath', explode(',', config('services.ripgrep.allowed_paths', storage_path('logs')))));
        
        $targetPath = $directory;
        if (!preg_match('/^([a-zA-Z]:[\/\\\]|\/)/', $targetPath)) {
            $targetPath = storage_path('logs/' . ltrim($targetPath, '/\\'));
        }

        $realTarget = realpath($targetPath);

        if (!$realTarget) {
            throw new \InvalidArgumentException("The path '{$directory}' could not be resolved or does not exist.");
        }

        $authorized = false;
        foreach ($allowedBases as $base) {
            if (str_starts_with($realTarget, $base)) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            $allowedList = implode(', ', array_map(fn($p) => basename($p) === 'logs' ? 'storage/logs' : $p, explode(',', config('services.ripgrep.allowed_paths', 'storage/logs'))));
            throw new \InvalidArgumentException("Unauthorized path. You can only scan within: {$allowedList}");
        }

        if ($this->isRipgrepAvailable()) {
            return $this->scanWithRipgrep($pattern, $realTarget);
        }

        return $this->scanWithPhp($pattern, $realTarget);
    }

    private function isRipgrepAvailable(): bool
    {
        $command = DIRECTORY_SEPARATOR === '\\' ? 'where rg' : 'which rg';
        exec($command, $output, $returnVar);
        return $returnVar === 0;
    }

    private function scanWithRipgrep(string $pattern, string $path): array
    {
        $process = new Process(['rg', '--json', '-i', '--no-ignore', '--hidden', '-a', $pattern, $path]);
        $process->setTimeout(60);

        try {
            $process->run();
            if (!$process->isSuccessful() && $process->getExitCode() !== 1) {
                throw new ProcessFailedException($process);
            }
            return $this->parseOutput($process->getOutput());
        } catch (\Exception $e) {
            Log::warning("Ripgrep failed, falling back to PHP: " . $e->getMessage());
            return $this->scanWithPhp($pattern, $path);
        }
    }

    private function scanWithPhp(string $pattern, string $path): array
    {
        $results = [];
        $files = is_file($path) ? [$path] : $this->getFilesRecursive($path);
        $regex = '/' . preg_quote($pattern, '/') . '/i';

        // If the user provided a valid regex pattern, we try to use it directly
        if (@preg_match($pattern, '') !== false) {
            $regex = str_starts_with($pattern, '/') ? $pattern : '/' . $pattern . '/i';
        }

        foreach ($files as $file) {
            if ($this->appearsBinary($file)) continue;

            $handle = @fopen($file, 'r');
            if (!$handle) continue;

            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if (preg_match($regex, $line)) {
                    $results[] = [
                        'file' => $file,
                        'line_number' => $lineNumber,
                        'content' => trim($line),
                    ];
                }

                if (count($results) > 1000) break 2; // Safety limit
            }
            fclose($handle);
        }

        return $results;
    }

    private function getFilesRecursive(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function appearsBinary(string $file): bool
    {
        $handle = @fopen($file, 'r');
        if (!$handle) return false;
        $contents = fread($handle, 512);
        fclose($handle);
        return str_contains($contents, "\0");
    }


    private function parseOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $results = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $data = json_decode($line, true);
            if (!$data) continue;

            if ($data['type'] === 'match') {
                $results[] = [
                    'file' => $data['data']['path']['text'],
                    'line_number' => $data['data']['line_number'],
                    'content' => $data['data']['lines']['text'] ?? '',
                ];
            }
        }

        return $results;
    }
}
