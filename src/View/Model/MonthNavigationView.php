<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class MonthNavigationView
{
    public function __construct(
        public string $monthName,
        public MonthLinkView $previous,
        public MonthLinkView $next,
    ) {
    }
}
