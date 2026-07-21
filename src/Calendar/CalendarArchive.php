<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final class CalendarArchive
{
    /** @var array<int, array<int, string>> */
    private array $titles = [];

    private ?ArchiveSummary $summary = null;

    public function __construct(
        private readonly string $directory,
        private readonly string $webPath = 'blog',
    ) {
    }

    public function post(int $month, int $day): ?CalendarPost
    {
        if (!is_file($this->filePath($month, $day, 'thmb'))) {
            return null;
        }

        return new CalendarPost(
            $this->title($month, $day),
            $this->imageUrl($month, $day, 'full'),
            $this->imageUrl($month, $day, 'thmb'),
        );
    }

    public function summary(): ArchiveSummary
    {
        if ($this->summary !== null) {
            return $this->summary;
        }

        /** @var array<int, int> $counts */
        $counts = array_fill(1, 12, 0);
        $pattern = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . '*_thmb.jpg';

        foreach (glob($pattern) ?: [] as $file) {
            if (preg_match('/^([1-9]|1[0-2])_([1-9]|[12][0-9]|3[01])_thmb\.jpg$/D', basename($file), $match) !== 1) {
                continue;
            }

            $month = (int) $match[1];
            $day = (int) $match[2];
            if ($day > cal_days_in_month(CAL_GREGORIAN, $month, 2000)) {
                continue;
            }

            $counts[$month]++;
        }

        $months = [];
        foreach ($counts as $month => $postCount) {
            $months[] = new ArchiveMonth($month, $postCount);
        }

        return $this->summary = new ArchiveSummary($months, array_sum($counts));
    }

    private function title(int $month, int $day): string
    {
        if (!array_key_exists($month, $this->titles)) {
            $this->titles[$month] = $this->loadTitles($month);
        }

        return $this->titles[$month][$day] ?? 'no subject';
    }

    /** @return array<int, string> */
    private function loadTitles(int $month): array
    {
        $file = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $month . '.txt';
        if (!is_file($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $titles = [];
        foreach ($lines as $line) {
            $separator = strpos($line, ' ');
            if ($separator === false) {
                continue;
            }

            $day = (int) substr($line, 0, $separator);
            if ($day >= 1 && $day <= 31) {
                $titles[$day] = trim(substr($line, $separator + 1));
            }
        }

        return $titles;
    }

    private function filePath(int $month, int $day, string $size): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR
            . $month . '_' . $day . '_' . $size . '.jpg';
    }

    private function imageUrl(int $month, int $day, string $size): string
    {
        return rtrim($this->webPath, '/') . '/' . $month . '_' . $day . '_' . $size . '.jpg';
    }
}
