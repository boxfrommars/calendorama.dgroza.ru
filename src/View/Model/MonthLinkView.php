<?php

declare(strict_types=1);

namespace Calendorama\View\Model;

final readonly class MonthLinkView
{
    public function __construct(
        public string $title,
        public ?string $href,
    ) {
    }
}
