<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use RuntimeException;

final class Html
{
    public static function canonical(string $html): string
    {
        $normalized = str_replace("\r\n", "\n", trim($html));
        $canonical = preg_replace('/>\s+</u', '><', $normalized);

        if ($canonical === null) {
            throw new RuntimeException('Could not canonicalize HTML.');
        }

        return $canonical;
    }

    private function __construct()
    {
    }
}
