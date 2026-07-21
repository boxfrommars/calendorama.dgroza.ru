<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\View\AssetUrlGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AssetUrlGeneratorTest extends TestCase
{
    public function testUsesStableContentHashAndChangesWithContent(): void
    {
        $directory = self::temporaryDirectory();

        try {
            file_put_contents($directory . '/style.css', 'first');
            $first = (new AssetUrlGenerator($directory))->versioned('/style.css');
            self::assertSame('/style.css?v=a7937b64b8ca', $first);

            file_put_contents($directory . '/style.css', 'second');
            $second = (new AssetUrlGenerator($directory))->versioned('/style.css');
            self::assertSame('/style.css?v=16367aacb67a', $second);
            self::assertNotSame($first, $second);
        } finally {
            @unlink($directory . '/style.css');
            @rmdir($directory);
        }
    }

    public function testRejectsMissingAsset(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Public asset does not exist: /calendorama-definitely-missing.css');

        (new AssetUrlGenerator(sys_get_temp_dir()))->versioned('/calendorama-definitely-missing.css');
    }

    private static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/calendorama-assets-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        return $directory;
    }
}
