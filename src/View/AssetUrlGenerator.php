<?php

declare(strict_types=1);

namespace Calendorama\View;

use RuntimeException;

final readonly class AssetUrlGenerator
{
    public function __construct(private string $publicDirectory)
    {
    }

    public function versioned(string $path): string
    {
        $publicPath = '/' . ltrim($path, '/');
        $file = rtrim($this->publicDirectory, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
        if (!is_file($file)) {
            throw new RuntimeException('Public asset does not exist: ' . $publicPath);
        }

        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException('Could not hash public asset: ' . $publicPath);
        }

        return $publicPath . '?v=' . substr($hash, 0, 12);
    }
}
