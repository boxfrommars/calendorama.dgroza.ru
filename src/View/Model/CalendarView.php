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
        public string $monthName,
        public ?string $previousTitle,
        public ?string $previousRoute,
        public ?string $nextTitle,
        public ?string $nextRoute,
    ) {
    }
}
