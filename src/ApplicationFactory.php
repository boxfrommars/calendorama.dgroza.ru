<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\View\AssetUrlGenerator;
use Calendorama\View\TemplateRenderer;
use Calendorama\View\ViewFactory;
use DateTimeImmutable;

final class ApplicationFactory
{
    public static function create(string $projectRoot, ?DateTimeImmutable $now = null): Application
    {
        $root = rtrim($projectRoot, '/\\');
        $now ??= new DateTimeImmutable();
        $templates = new TemplateRenderer($root . '/templates');
        $archive = new CalendarArchive($root . '/content/archive.json');
        $views = new ViewFactory(
            $archive,
            $now,
            new AssetUrlGenerator($root . '/public'),
        );

        return new Application($templates, $views);
    }

    private function __construct()
    {
    }
}
