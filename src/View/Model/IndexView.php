<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class IndexView
{
    /** @param list<ArchiveMonthView> $months */
    public function __construct(
        public int $currentYear,
        public int $currentMonth,
        public string $initialRoute,
        public int $daysInYear,
        public int $postCount,
        public array $months,
        public string $styleUrl,
        public string $scriptUrl,
    ) {
    }
}
