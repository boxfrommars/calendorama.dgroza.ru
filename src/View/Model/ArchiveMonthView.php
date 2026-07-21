<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class ArchiveMonthView
{
    public function __construct(
        public string $name,
        public int $postCount,
        public string $route,
    ) {
    }
}
