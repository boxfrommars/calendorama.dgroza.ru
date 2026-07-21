<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarContent;
use PHPUnit\Framework\TestCase;

final class CalendarContentTest extends TestCase
{
    public function testReturnsCompletePostOrNull(): void
    {
        $content = new CalendarContent(dirname(__DIR__) . '/public/blog');

        $post = $content->post(3, 1);
        self::assertNotNull($post);
        self::assertSame('настя, кардамон, карнавал, дг: снегопад', $post->title);
        self::assertSame('blog/3_1_full.jpg', $post->fullUrl);
        self::assertSame('blog/3_1_thmb.jpg', $post->thumbnailUrl);
        self::assertNull($content->post(3, 2));
    }

    public function testFallsBackToNoSubject(): void
    {
        $directory = self::temporaryDirectory();

        try {
            touch($directory . '/3_1_thmb.jpg');
            $post = (new CalendarContent($directory))->post(3, 1);

            self::assertNotNull($post);
            self::assertSame('no subject', $post->title);
        } finally {
            @unlink($directory . '/3_1_thmb.jpg');
            @rmdir($directory);
        }
    }

    private static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/calendorama-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        return $directory;
    }
}
