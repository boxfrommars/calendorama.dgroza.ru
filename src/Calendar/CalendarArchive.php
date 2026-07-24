<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

/**
 * @phpstan-type ArchivePost array{month: int, day: int, title: string, publishedAt: DateTimeImmutable}
 */
final class CalendarArchive
{
    /** @var list<ArchivePost>|null */
    private ?array $posts = null;

    /** @var array<int, array<int, string>> */
    private array $titles = [];

    public function __construct(private readonly string $manifestFile)
    {
    }

    public function postTitle(int $month, int $day): ?string
    {
        $this->load();

        return $this->titles[$month][$day] ?? null;
    }

    /** @return array<int, int> Counts indexed by month 1..12. */
    public function postCountsByMonth(): array
    {
        $this->load();
        $counts = array_fill(1, 12, 0);
        foreach ($this->titles as $month => $titles) {
            $counts[$month] = count($titles);
        }

        return $counts;
    }

    /** @return list<ArchivePost> */
    public function posts(): array
    {
        return $this->load();
    }

    /** @return list<ArchivePost> */
    private function load(): array
    {
        if ($this->posts !== null) {
            return $this->posts;
        }

        [$posts, $titles] = $this->buildIndexes(
            $this->decodeManifest($this->readManifest()),
        );
        $this->titles = $titles;

        return $this->posts = $posts;
    }

    /**
     * @param list<mixed> $records
     * @return array{list<ArchivePost>, array<int, array<int, string>>}
     */
    private function buildIndexes(array $records): array
    {
        /** @var list<ArchivePost> $posts */
        $posts = [];
        /** @var array<int, array<int, string>> $titles */
        $titles = [];
        foreach ($records as $index => $record) {
            $post = $this->parseRecord($record, $index);
            $month = $post['month'];
            $day = $post['day'];
            if (isset($titles[$month][$day])) {
                $this->invalidRecord($index, sprintf('duplicate date %d-%d', $month, $day));
            }

            $titles[$month][$day] = $post['title'];
            $posts[] = $post;
        }

        return [$posts, $titles];
    }

    private function readManifest(): string
    {
        $json = @file_get_contents($this->manifestFile);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read archive manifest "%s".', $this->manifestFile));
        }

        return $json;
    }

    /** @return list<mixed> */
    private function decodeManifest(string $json): array
    {
        try {
            $records = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(
                sprintf('Invalid archive manifest "%s": %s', $this->manifestFile, $exception->getMessage()),
                previous: $exception,
            );
        }

        if (!is_array($records) || !array_is_list($records)) {
            throw new UnexpectedValueException(
                sprintf('Invalid archive manifest "%s": expected a JSON array.', $this->manifestFile),
            );
        }

        return $records;
    }

    /** @return ArchivePost */
    private function parseRecord(mixed $record, int $index): array
    {
        if (
            !is_array($record)
            || count($record) !== 4
            || !array_key_exists('month', $record)
            || !array_key_exists('day', $record)
            || !array_key_exists('title', $record)
            || !array_key_exists('publishedAt', $record)
        ) {
            $this->invalidRecord($index, 'expected exactly month, day, title and publishedAt fields');
        }

        $month = $record['month'];
        $day = $record['day'];
        $title = $record['title'];

        if (!is_int($month) || $month < 1 || $month > 12) {
            $this->invalidRecord($index, 'month must be an integer from 1 to 12');
        }
        if (
            !is_int($day)
            || $day < 1
            || $day > (new CalendarMonth($month, 2000))->daysInMonth
        ) {
            $this->invalidRecord($index, 'day must be valid for the recurring calendar month');
        }
        if (!is_string($title) || trim($title) === '') {
            $this->invalidRecord($index, 'title must be a non-empty string');
        }

        $publishedDate = $this->parsePublishedAt($record['publishedAt'], $index);
        if (
            (int) $publishedDate->format('n') !== $month
            || (int) $publishedDate->format('j') !== $day
        ) {
            $this->invalidRecord($index, 'publishedAt month and day must match the calendar date');
        }

        return [
            'month' => $month,
            'day' => $day,
            'title' => $title,
            'publishedAt' => $publishedDate,
        ];
    }

    private function parsePublishedAt(mixed $value, int $index): DateTimeImmutable
    {
        if (!is_string($value)) {
            $this->invalidRecord($index, 'publishedAt must be a UTC timestamp in YYYY-MM-DDTHH:MM:SSZ format');
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $value,
            new DateTimeZone('UTC'),
        );
        if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
            $this->invalidRecord($index, 'publishedAt must be a UTC timestamp in YYYY-MM-DDTHH:MM:SSZ format');
        }

        return $date;
    }

    private function invalidRecord(int $index, string $message): never
    {
        throw new UnexpectedValueException(sprintf(
            'Invalid archive manifest "%s" at record %d: %s.',
            $this->manifestFile,
            $index + 1,
            $message,
        ));
    }
}
