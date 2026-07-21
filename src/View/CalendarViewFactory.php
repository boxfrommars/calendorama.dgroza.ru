<?php

declare(strict_types=1);

namespace Calendorama\View;

use Calendorama\Calendar\CalendarContent;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\View\Model\CalendarCellView;
use Calendorama\View\Model\CalendarPostView;
use Calendorama\View\Model\CalendarView;
use Calendorama\View\Model\MonthLinkView;
use Calendorama\View\Model\MonthNavigationView;
use Psr\Clock\ClockInterface;

final readonly class CalendarViewFactory
{
    private const WEEKS = 6;

    public function __construct(
        private CalendarContent $content,
        private ClockInterface $clock,
    ) {
    }

    public function create(CalendarMonth $month): CalendarView
    {
        $today = $this->todayIn($month);

        return new CalendarView(
            $month->month,
            $month->year,
            $this->weeks($month, $today),
            new MonthNavigationView(
                $month->name,
                self::navigationLink(
                    $month->previousMonthName,
                    $month->previousMonth,
                    $month->previousYear,
                ),
                self::navigationLink(
                    $month->nextMonthName,
                    $month->nextMonth,
                    $month->nextYear,
                ),
            ),
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
        $post = $this->content->post($month->month, $day);

        $postView = $post === null ? null : new CalendarPostView(
            '#d' . $month->year . '_' . $month->month . '_' . $day,
            $post->title,
            $post->fullUrl,
            $post->thumbnailUrl,
        );

        return new CalendarCellView($day, $postView, $day === $today);
    }

    private function todayIn(CalendarMonth $month): ?int
    {
        $today = $this->clock->now();

        if ($month->month !== (int) $today->format('n') || $month->year !== (int) $today->format('Y')) {
            return null;
        }

        return (int) $today->format('j');
    }

    private static function navigationLink(string $monthName, int $month, int $year): MonthLinkView
    {
        return new MonthLinkView(
            $monthName . ' ' . $year,
            '/#d' . $year . '_' . $month,
        );
    }
}
