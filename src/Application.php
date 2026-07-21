<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Http\Controller\CalendarController;
use Calendorama\Http\Controller\IndexController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class Application
{
    public function __construct(
        private IndexController $indexController,
        private CalendarController $calendarController,
    ) {
    }

    public function index(): Response
    {
        return ($this->indexController)();
    }

    public function calendar(Request $request): Response
    {
        return ($this->calendarController)($request);
    }
}
