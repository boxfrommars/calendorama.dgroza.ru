<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final readonly class ArchiveSummary
{
    /** @param list<ArchiveMonth> $months */
    public function __construct(
        public array $months,
        public int $postCount,
    ) {
    }
}
