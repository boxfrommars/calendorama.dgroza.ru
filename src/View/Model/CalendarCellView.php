<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class CalendarCellView
{
    public function __construct(
        public ?int $day,
        public ?CalendarPostView $post,
        public bool $isToday,
    ) {
    }
}
