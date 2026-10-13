<?php

namespace App\Support;

use RuntimeException;

final class ViteBuildVerifier
{
    /** @return array{entries:int,files:int} */
    public function verify(string $buildDirectory): array
    {
        $manifestPath = rtrim($buildDirectory, '/\\').DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException("Vite manifest is missing: {$manifestPath}");
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Vite manifest is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || ! isset($manifest['resources/js/app.tsx']) || ! is_array($manifest['resources/js/app.tsx'])) {
            throw new RuntimeException('Vite manifest does not contain the RAOZA application entry.');
        }

        $files = [];
        foreach ($manifest as $key => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException("Vite manifest entry is invalid: {$key}");
            }
            foreach (['imports', 'dynamicImports'] as $relation) {
                foreach ($entry[$relation] ?? [] as $reference) {
                    if (! is_string($reference) || ! array_key_exists($reference, $manifest)) {
                        throw new RuntimeException("Vite manifest entry {$key} has a missing {$relation} reference.");
                    }
                }
            }
            foreach (array_merge(isset($entry['file']) ? [$entry['file']] : [], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                if (! is_string($file) || ! $this->safeRelativePath($file)) {
                    throw new RuntimeException("Vite manifest contains an unsafe asset path in {$key}.");
                }
                $files[$file] = true;
            }
        }

        foreach (array_keys($files) as $file) {
            if (! is_file(rtrim($buildDirectory, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file))) {
                throw new RuntimeException("Vite manifest references a missing asset: {$file}");
            }
        }

        return ['entries' => count($manifest), 'files' => count($files)];
    }

    private function safeRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_starts_with($path, '\\')
            && ! preg_match('/^[A-Za-z]:/', $path)
            && ! preg_match('#(^|/)\.\.(/|$)#', $path)
            && ! str_contains($path, "\0");
    }
}
