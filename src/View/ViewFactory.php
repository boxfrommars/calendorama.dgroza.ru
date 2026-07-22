<?php

declare(strict_types=1);

namespace Calendorama\View;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Calendar\CalendarMonth;
use DateTimeImmutable;

/**
 * @phpstan-type ArchiveMonthView array{name: string, postCount: int, route: string}
 * @phpstan-type CalendarPostView array{route: string, title: string, ariaLabel: string, fullUrl: string, thumbnailUrl: string}
 * @phpstan-type CalendarCellView array{day: int|null, post: CalendarPostView|null, isToday: bool}
 * @phpstan-type CalendarView array{month: int, year: int, weeks: list<list<CalendarCellView>>, monthName: string, previousTitle: string|null, previousRoute: string|null, nextTitle: string|null, nextRoute: string|null}
 * @phpstan-type IndexView array{initialRoute: string, daysInYear: int, postCount: int, months: list<ArchiveMonthView>, styleUrl: string, scriptUrl: string}
 */
final readonly class ViewFactory
{
    private const WEEKS = 6;

    public function __construct(
        private CalendarArchive $archive,
        private DateTimeImmutable $now,
        private AssetUrlGenerator $assets,
    ) {
    }

    /** @return IndexView */
    public function index(): array
    {
        $year = (int) $this->now->format('Y');
        $month = (int) $this->now->format('n');
        $postCounts = $this->archive->postCountsByMonth();
        $months = [];

        foreach ($postCounts as $monthNumber => $postCount) {
            $route = self::monthRoute($year, $monthNumber);
            $months[] = [
                'name' => (new CalendarMonth($monthNumber, $year))->name,
                'postCount' => $postCount,
                'route' => $route,
            ];
        }

        return [
            'initialRoute' => self::monthRoute($year, $month),
            'daysInYear' => 365 + (int) $this->now->format('L'),
            'postCount' => array_sum($postCounts),
            'months' => $months,
            'styleUrl' => $this->assets->versioned('/style.css'),
            'scriptUrl' => $this->assets->versioned('/script.js'),
        ];
    }

    /** @return CalendarView */
    public function calendar(CalendarMonth $month): array
    {
        $today = $this->todayIn($month);
        $previous = $month->previous();
        $next = $month->next();

        return [
            'month' => $month->month,
            'year' => $month->year,
            'weeks' => $this->weeks($month, $today),
            'monthName' => $month->name,
            'previousTitle' => $previous === null ? null : $previous->name . ' ' . $previous->year,
            'previousRoute' => $previous === null ? null : self::monthRoute($previous->year, $previous->month),
            'nextTitle' => $next === null ? null : $next->name . ' ' . $next->year,
            'nextRoute' => $next === null ? null : self::monthRoute($next->year, $next->month),
        ];
    }

    /** @return list<list<CalendarCellView>> */
    private function weeks(CalendarMonth $month, ?int $today): array
    {
        $weeks = [];
        $cellNumber = 1;

        for ($weekNumber = 1; $weekNumber <= self::WEEKS; $weekNumber++) {
            $week = [];
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $week[] = $this->cell($month, $cellNumber, $today);
                $cellNumber++;
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** @return CalendarCellView */
    private function cell(CalendarMonth $month, int $cellNumber, ?int $today): array
    {
        if ($cellNumber < $month->firstWeekday || $cellNumber >= $month->firstWeekday + $month->daysInMonth) {
            return ['day' => null, 'post' => null, 'isToday' => false];
        }

        $day = $cellNumber - $month->firstWeekday + 1;
        $title = $this->archive->postTitle($month->month, $day);
        $postView = $title === null ? null : [
            'route' => '#d' . $month->year . '_' . $month->month . '_' . $day,
            'title' => $title,
            'ariaLabel' => sprintf('%d.%02d.%04d — %s', $day, $month->month, $month->year, $title),
            'fullUrl' => self::imageUrl($month->month, $day, 'full'),
            'thumbnailUrl' => self::imageUrl($month->month, $day, 'thmb'),
        ];

        return ['day' => $day, 'post' => $postView, 'isToday' => $day === $today];
    }

    private function todayIn(CalendarMonth $month): ?int
    {
        if ($month->month !== (int) $this->now->format('n') || $month->year !== (int) $this->now->format('Y')) {
            return null;
        }

        return (int) $this->now->format('j');
    }

    private static function monthRoute(int $year, int $month): string
    {
        return '#d' . $year . '_' . $month;
    }

    private static function imageUrl(int $month, int $day, string $size): string
    {
        return 'blog/' . $month . '_' . $day . '_' . $size . '.jpg';
    }
}
