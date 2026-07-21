<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class CalendarView
{
    /**
     * @param list<list<CalendarCellView>> $weeks
     */
    public function __construct(
        public string $class,
        public array $weeks,
        public MonthNavigationView $navigation,
    ) {
    }
}
