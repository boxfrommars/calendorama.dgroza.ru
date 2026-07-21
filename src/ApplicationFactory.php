<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Http\Controller\CalendarController;
use Calendorama\Http\Controller\IndexController;
use Calendorama\View\AssetUrlGenerator;
use Calendorama\View\CalendarViewFactory;
use Calendorama\View\IndexViewFactory;
use Calendorama\View\TemplateRenderer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

final class ApplicationFactory
{
    public static function create(string $projectRoot, ?ClockInterface $clock = null): Application
    {
        $root = rtrim($projectRoot, '/\\');
        $clock ??= new NativeClock();
        $templates = new TemplateRenderer($root . '/templates');
        $archive = new CalendarArchive($root . '/public/blog');
        $calendarViews = new CalendarViewFactory($archive, $clock);
        $indexViews = new IndexViewFactory(
            $archive,
            $clock,
            new AssetUrlGenerator($root . '/public'),
        );

        return new Application(
            new IndexController($templates, $indexViews),
            new CalendarController($calendarViews, $templates),
        );
    }

    private function __construct()
    {
    }
}
