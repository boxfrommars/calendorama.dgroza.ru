<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class CalendarPostView
{
    public function __construct(
        public string $anchorId,
        public string $title,
        public string $fullUrl,
        public string $thumbnailUrl,
    ) {
    }
}
