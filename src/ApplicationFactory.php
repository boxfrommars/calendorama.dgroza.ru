<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Calendar\CalendarContent;
use Calendorama\Http\Controller\CalendarController;
use Calendorama\Http\Controller\IndexController;
use Calendorama\View\CalendarViewFactory;
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
        $calendarViews = new CalendarViewFactory(
            new CalendarContent($root . '/public/blog'),
            $clock,
        );

        return new Application(
            new IndexController($templates, $clock),
            new CalendarController($calendarViews, $templates),
        );
    }

    private function __construct()
    {
    }
}
