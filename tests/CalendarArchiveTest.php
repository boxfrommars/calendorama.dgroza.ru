<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

final class CalendarArchiveTest extends TestCase
{
    public function testReturnsPostTitleOrNull(): void
    {
        $archive = self::projectArchive();

        self::assertSame('настя, кардамон, карнавал, дг: снегопад', $archive->postTitle(3, 1));
        self::assertNull($archive->postTitle(3, 2));
    }

    public function testBuildsPostCountsFromManifest(): void
    {
        $counts = self::projectArchive()->postCountsByMonth();

        self::assertCount(12, $counts);
        self::assertSame(53, array_sum($counts));
        self::assertSame([5, 2, 27, 5, 2, 2, 1, 2, 1, 0, 2, 4], array_values($counts));
    }

    public function testLoadsManifestOnlyOncePerArchiveInstance(): void
    {
        $json = json_encode(
            [['month' => 3, 'day' => 1, 'title' => 'title']],
            JSON_THROW_ON_ERROR,
        );
        $file = self::temporaryManifest($json);
        $archive = new CalendarArchive($file);

        try {
            self::assertSame('title', $archive->postTitle(3, 1));
            self::assertTrue(unlink($file));
            self::assertSame(1, array_sum($archive->postCountsByMonth()));
        } finally {
            @unlink($file);
        }
    }

    public function testRejectsMissingManifest(): void
    {
        $file = sys_get_temp_dir() . '/missing-calendorama-' . bin2hex(random_bytes(8)) . '.json';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read archive manifest');

        (new CalendarArchive($file))->postCountsByMonth();
    }

    public function testRejectsMalformedJson(): void
    {
        $file = self::temporaryManifest('{not valid JSON');

        try {
            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage('Invalid archive manifest');

            (new CalendarArchive($file))->postCountsByMonth();
        } finally {
            @unlink($file);
        }
    }

    public function testRejectsNonArrayRoot(): void
    {
        $file = self::temporaryManifest('{"month": 3}');

        try {
            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage('expected a JSON array');

            (new CalendarArchive($file))->postCountsByMonth();
        } finally {
            @unlink($file);
        }
    }

    /** @param list<mixed> $records */
    #[DataProvider('invalidRecords')]
    public function testRejectsInvalidRecords(array $records, string $message): void
    {
        $json = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $file = self::temporaryManifest($json);

        try {
            $this->expectException(UnexpectedValueException::class);
            $this->expectExceptionMessage($message);

            (new CalendarArchive($file))->postCountsByMonth();
        } finally {
            @unlink($file);
        }
    }

    /** @return iterable<string, array{list<mixed>, string}> */
    public static function invalidRecords(): iterable
    {
        yield 'missing field' => [[['month' => 3, 'day' => 1]], 'record 1: expected exactly'];
        yield 'unknown field' => [[['month' => 3, 'day' => 1, 'title' => 'title', 'extra' => true]], 'record 1: expected exactly'];
        yield 'month type' => [[['month' => '3', 'day' => 1, 'title' => 'title']], 'month must be an integer'];
        yield 'month range' => [[['month' => 13, 'day' => 1, 'title' => 'title']], 'month must be an integer'];
        yield 'day type' => [[['month' => 3, 'day' => '1', 'title' => 'title']], 'day must be valid'];
        yield 'impossible date' => [[['month' => 2, 'day' => 30, 'title' => 'title']], 'day must be valid'];
        yield 'empty title' => [[['month' => 3, 'day' => 1, 'title' => '  ']], 'title must be a non-empty string'];
        yield 'title type' => [[['month' => 3, 'day' => 1, 'title' => 42]], 'title must be a non-empty string'];
        yield 'duplicate date' => [[
            ['month' => 3, 'day' => 1, 'title' => 'first'],
            ['month' => 3, 'day' => 1, 'title' => 'second'],
        ], 'record 2: duplicate date 3-1'];
    }

    private static function projectArchive(): CalendarArchive
    {
        return new CalendarArchive(dirname(__DIR__) . '/content/archive.json');
    }

    private static function temporaryManifest(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'calendorama-');
        self::assertIsString($file);
        self::assertNotFalse(file_put_contents($file, $contents));

        return $file;
    }
}
