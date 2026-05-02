<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FileIntegrityService
{
    /**
     * Get hashes for files in specified paths
     */
    public function getHashes(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $hashes[$path] = hash_file('sha256', $path);
            } elseif (is_dir($path)) {
                $hashes = array_merge($hashes, $this->hashDirectory($path));
            }
        }

        return $hashes;
    }

    protected function hashDirectory(string $dir): array
    {
        $hashes = [];
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $fullPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

            if (is_file($fullPath)) {
                $hashes[$fullPath] = hash_file('sha256', $fullPath);
            } elseif (is_dir($fullPath)) {
                $hashes = array_merge($hashes, $this->hashDirectory($fullPath));
            }
        }

        return $hashes;
    }
}
