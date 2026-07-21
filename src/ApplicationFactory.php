<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Calendar\CalendarContent;
use Calendorama\Http\Controller\CalendarController;
use Calendorama\Http\Controller\IndexController;
use Calendorama\View\CalendarViewFactory;
use Calendorama\View\TemplateRenderer;
use Symfony\Component\Clock\NativeClock;

final class ApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $root = rtrim($projectRoot, '/\\');
        $templates = new TemplateRenderer($root . '/templates');
        $calendarViews = new CalendarViewFactory(
            new CalendarContent($root . '/public/blog'),
            new NativeClock(),
        );

        return new Application(
            new IndexController($templates),
            new CalendarController($calendarViews, $templates),
        );
    }

    private function __construct()
    {
    }
}
