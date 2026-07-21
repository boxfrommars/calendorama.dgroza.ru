<?php

declare(strict_types=1);

namespace Calendorama\View;

use RuntimeException;

final class AssetUrlGenerator
{
    /** @var array<string, string> */
    private array $urls = [];

    public function __construct(private readonly string $publicDirectory)
    {
    }

    public function versioned(string $path): string
    {
        if (isset($this->urls[$path])) {
            return $this->urls[$path];
        }

        $publicPath = '/' . ltrim($path, '/');
        $file = rtrim($this->publicDirectory, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
        if (!is_file($file)) {
            throw new RuntimeException('Public asset does not exist: ' . $publicPath);
        }

        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new RuntimeException('Could not hash public asset: ' . $publicPath);
        }

        return $this->urls[$path] = $publicPath . '?v=' . substr($hash, 0, 12);
    }
}
