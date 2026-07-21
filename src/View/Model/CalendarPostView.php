<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class CalendarPostView
{
    public function __construct(
        public string $route,
        public string $title,
        public string $ariaLabel,
        public string $fullUrl,
        public string $thumbnailUrl,
    ) {
    }
}
