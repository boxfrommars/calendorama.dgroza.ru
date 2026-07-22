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

        $json = @file_get_contents($this->manifestFile);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read archive manifest "%s".', $this->manifestFile));
        }

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

        /** @var list<ArchivePost> $posts */
        $posts = [];
        /** @var array<int, array<int, string>> $titles */
        $titles = [];
        foreach ($records as $index => $record) {
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
            $publishedAt = $record['publishedAt'];

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
            if (!is_string($publishedAt)) {
                $this->invalidRecord($index, 'publishedAt must be a UTC timestamp in YYYY-MM-DDTHH:MM:SSZ format');
            }

            $publishedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i:s\Z',
                $publishedAt,
                new DateTimeZone('UTC'),
            );
            if (
                $publishedDate === false
                || $publishedDate->format('Y-m-d\TH:i:s\Z') !== $publishedAt
            ) {
                $this->invalidRecord($index, 'publishedAt must be a UTC timestamp in YYYY-MM-DDTHH:MM:SSZ format');
            }
            if (
                (int) $publishedDate->format('n') !== $month
                || (int) $publishedDate->format('j') !== $day
            ) {
                $this->invalidRecord($index, 'publishedAt month and day must match the calendar date');
            }
            if (isset($titles[$month][$day])) {
                $this->invalidRecord($index, sprintf('duplicate date %d-%d', $month, $day));
            }

            $titles[$month][$day] = $title;
            $posts[] = [
                'month' => $month,
                'day' => $day,
                'title' => $title,
                'publishedAt' => $publishedDate,
            ];
        }

        $this->titles = $titles;

        return $this->posts = $posts;
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
