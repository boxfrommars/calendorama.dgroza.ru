<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class CalendarView
{
    /**
     * @param list<list<CalendarCellView>> $weeks
     */
    public function __construct(
        public int $month,
        public int $year,
        public array $weeks,
        public MonthNavigationView $navigation,
    ) {
    }
}
