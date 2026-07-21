<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final class CalendarContent
{
    /** @var array<int, array<int, string>> */
    private array $titles = [];

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
