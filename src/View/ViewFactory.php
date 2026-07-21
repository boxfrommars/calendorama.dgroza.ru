<?php

declare(strict_types=1);

namespace Calendorama\View;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\View\Model\ArchiveMonthView;
use Calendorama\View\Model\CalendarCellView;
use Calendorama\View\Model\CalendarPostView;
use Calendorama\View\Model\CalendarView;
use Calendorama\View\Model\IndexView;
use DateTimeImmutable;

final readonly class ViewFactory
{
    private const WEEKS = 6;

    public function __construct(
        private CalendarArchive $archive,
        private DateTimeImmutable $now,
        private AssetUrlGenerator $assets,
    ) {
    }

    public function index(): IndexView
    {
        $year = (int) $this->now->format('Y');
        $month = (int) $this->now->format('n');
        $postCounts = $this->archive->postCountsByMonth();
        $months = [];

        foreach ($postCounts as $monthNumber => $postCount) {
            $route = self::monthRoute($year, $monthNumber);
            $months[] = new ArchiveMonthView(
                (new CalendarMonth($monthNumber, $year))->name,
                $postCount,
                $route,
            );
        }

        return new IndexView(
            self::monthRoute($year, $month),
            365 + (int) $this->now->format('L'),
            array_sum($postCounts),
            $months,
            $this->assets->versioned('/style.css'),
            $this->assets->versioned('/script.js'),
        );
    }

    public function calendar(CalendarMonth $month): CalendarView
    {
        $today = $this->todayIn($month);
        $previous = $month->previous();
        $next = $month->next();

        return new CalendarView(
            $month->month,
            $month->year,
            $this->weeks($month, $today),
            $month->name,
            $previous === null ? null : $previous->name . ' ' . $previous->year,
            $previous === null ? null : self::monthRoute($previous->year, $previous->month),
            $next === null ? null : $next->name . ' ' . $next->year,
            $next === null ? null : self::monthRoute($next->year, $next->month),
        );
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

    private function cell(CalendarMonth $month, int $cellNumber, ?int $today): CalendarCellView
    {
        if ($cellNumber < $month->firstWeekday || $cellNumber >= $month->firstWeekday + $month->daysInMonth) {
            return new CalendarCellView(null, null, false);
        }

        $day = $cellNumber - $month->firstWeekday + 1;
        $post = $this->archive->post($month->month, $day);
        $postView = $post === null ? null : new CalendarPostView(
            '#d' . $month->year . '_' . $month->month . '_' . $day,
            $post->title,
            sprintf('%d.%02d.%04d — %s', $day, $month->month, $month->year, $post->title),
            $post->fullUrl,
            $post->thumbnailUrl,
        );

        return new CalendarCellView($day, $postView, $day === $today);
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
}
