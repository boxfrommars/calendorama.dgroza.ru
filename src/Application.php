<?php

declare(strict_types=1);

namespace Calendorama;

use Calendorama\Calendar\CalendarMonth;
use Calendorama\Http\CalendarParameters;
use Calendorama\View\TemplateRenderer;
use Calendorama\View\ViewFactory;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class Application
{
    public function __construct(
        private TemplateRenderer $templates,
        private ViewFactory $views,
    ) {
    }

    public function index(): Response
    {
        return self::htmlResponse(
            $this->templates->render('index.html.twig', ['view' => $this->views->index()]),
        );
    }

    public function calendar(Request $request): Response
    {
        if (!$request->isMethod('GET')) {
            return new Response(
                'Method Not Allowed.',
                Response::HTTP_METHOD_NOT_ALLOWED,
                [
                    'Allow' => 'GET',
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                ],
            );
        }

        try {
            $parameters = CalendarParameters::fromArray($request->query->all());
            $month = new CalendarMonth($parameters->month, $parameters->year);
        } catch (InvalidArgumentException) {
            return self::htmlResponse(
                'Invalid calendar date.',
                Response::HTTP_BAD_REQUEST,
                ['Cache-Control' => 'no-store'],
            );
        }

        return self::htmlResponse(
            $this->templates->render(
                'calendar-fragment.html.twig',
                ['calendar' => $this->views->calendar($month)],
            ),
            headers: ['Cache-Control' => 'no-store'],
        );
    }

    /** @param array<string, string> $headers */
    private static function htmlResponse(
        string $content,
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): Response {
        return new Response(
            $content,
            $status,
            ['Content-Type' => 'text/html; charset=utf-8'] + $headers,
        );
    }
}
