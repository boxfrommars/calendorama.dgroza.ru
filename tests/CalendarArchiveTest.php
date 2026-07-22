<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use DateTimeImmutable;
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

    public function testReturnsPostsWithValidatedPublicationDates(): void
    {
        $posts = self::projectArchive()->posts();

        self::assertCount(53, $posts);
        self::assertInstanceOf(DateTimeImmutable::class, $posts[0]['publishedAt']);
        self::assertSame('2010-01-07T06:00:00Z', $posts[0]['publishedAt']->format('Y-m-d\TH:i:s\Z'));

        $marchTwentyFifth = array_values(array_filter(
            $posts,
            static fn (array $post): bool => $post['month'] === 3 && $post['day'] === 25,
        ));
        self::assertCount(1, $marchTwentyFifth);
        self::assertSame(
            '2009-03-25T06:00:00Z',
            $marchTwentyFifth[0]['publishedAt']->format('Y-m-d\TH:i:s\Z'),
        );
    }

    public function testLoadsManifestOnlyOncePerArchiveInstance(): void
    {
        $json = json_encode(
            [self::validRecord()],
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
        yield 'missing field' => [[['month' => 3, 'day' => 1, 'title' => 'title']], 'record 1: expected exactly'];
        yield 'unknown field' => [[self::validRecord(['extra' => true])], 'record 1: expected exactly'];
        yield 'month type' => [[self::validRecord(['month' => '3'])], 'month must be an integer'];
        yield 'month range' => [[self::validRecord(['month' => 13])], 'month must be an integer'];
        yield 'day type' => [[self::validRecord(['day' => '1'])], 'day must be valid'];
        yield 'impossible date' => [[self::validRecord(['month' => 2, 'day' => 30])], 'day must be valid'];
        yield 'empty title' => [[self::validRecord(['title' => '  '])], 'title must be a non-empty string'];
        yield 'title type' => [[self::validRecord(['title' => 42])], 'title must be a non-empty string'];
        yield 'publishedAt type' => [[self::validRecord(['publishedAt' => 42])], 'publishedAt must be a UTC timestamp'];
        yield 'publishedAt format' => [[self::validRecord(['publishedAt' => '2009-03-01 04:00:00Z'])], 'publishedAt must be a UTC timestamp'];
        yield 'publishedAt date mismatch' => [[self::validRecord(['publishedAt' => '2009-03-02T04:00:00Z'])], 'publishedAt month and day must match'];
        yield 'duplicate date' => [[
            self::validRecord(['title' => 'first']),
            self::validRecord(['title' => 'second']),
        ], 'record 2: duplicate date 3-1'];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function validRecord(array $overrides = []): array
    {
        return array_replace([
            'month' => 3,
            'day' => 1,
            'title' => 'title',
            'publishedAt' => '2009-03-01T04:00:00Z',
        ], $overrides);
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
