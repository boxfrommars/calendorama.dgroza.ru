<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final readonly class ArchiveMonth
{
    public function __construct(
        public int $month,
        public int $postCount,
    ) {
    }
}
