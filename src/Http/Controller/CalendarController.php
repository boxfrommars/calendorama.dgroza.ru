<?php

declare(strict_types=1);

namespace Calendorama\Http\Controller;

use Calendorama\Calendar\CalendarMonth;
use Calendorama\Http\CalendarParameters;
use Calendorama\View\CalendarViewFactory;
use Calendorama\View\TemplateRenderer;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CalendarController
{
    public function __construct(
        private CalendarViewFactory $calendarViews,
        private TemplateRenderer $templates,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $parameters = CalendarParameters::fromArray($request->request->all());
            $month = new CalendarMonth($parameters->month, $parameters->year);
        } catch (InvalidArgumentException) {
            return self::htmlResponse('Invalid calendar date.', Response::HTTP_BAD_REQUEST);
        }

        $view = $this->calendarViews->create($month);

        return self::htmlResponse(
            $this->templates->render('calendar-fragment.html.twig', ['calendar' => $view]),
        );
    }

    private static function htmlResponse(string $content, int $status = Response::HTTP_OK): Response
    {
        return new Response($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
