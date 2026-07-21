<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final readonly class CalendarPost
{
    public function __construct(
        public string $title,
        public string $fullUrl,
        public string $thumbnailUrl,
    ) {
    }
}
