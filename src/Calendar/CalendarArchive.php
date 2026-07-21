<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

use JsonException;
use RuntimeException;
use UnexpectedValueException;

final class CalendarArchive
{
    /** @var array<int, array<int, CalendarPost>>|null */
    private ?array $posts = null;

    private ?ArchiveSummary $summary = null;

    public function __construct(
        private readonly string $manifestFile,
        private readonly string $webPath = 'blog',
    ) {
    }

    public function post(int $month, int $day): ?CalendarPost
    {
        return $this->posts()[$month][$day] ?? null;
    }

    public function summary(): ArchiveSummary
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        $counts = array_fill(1, 12, 0);
        foreach ($this->posts() as $month => $posts) {
            $counts[$month] = count($posts);
        }

        $months = [];
        foreach ($counts as $month => $postCount) {
            $months[] = new ArchiveMonth($month, $postCount);
        }

        return $this->summary = new ArchiveSummary($months, array_sum($counts));
    }

    /** @return array<int, array<int, CalendarPost>> */
    private function posts(): array
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

        /** @var array<int, array<int, CalendarPost>> $posts */
        $posts = [];
        foreach ($records as $index => $record) {
            if (
                !is_array($record)
                || count($record) !== 3
                || !array_key_exists('month', $record)
                || !array_key_exists('day', $record)
                || !array_key_exists('title', $record)
            ) {
                $this->invalidRecord($index, 'expected exactly month, day and title fields');
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
                || $day > cal_days_in_month(CAL_GREGORIAN, $month, 2000)
            ) {
                $this->invalidRecord($index, 'day must be valid for the recurring calendar month');
            }
            if (!is_string($title) || trim($title) === '') {
                $this->invalidRecord($index, 'title must be a non-empty string');
            }
            if (isset($posts[$month][$day])) {
                $this->invalidRecord($index, sprintf('duplicate date %d-%d', $month, $day));
            }

            $posts[$month][$day] = new CalendarPost(
                $title,
                $this->imageUrl($month, $day, 'full'),
                $this->imageUrl($month, $day, 'thmb'),
            );
        }

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

    private function imageUrl(int $month, int $day, string $size): string
    {
        return rtrim($this->webPath, '/') . '/' . $month . '_' . $day . '_' . $size . '.jpg';
    }
}
