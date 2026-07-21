<?php

declare(strict_types=1);

namespace Calendorama\Http\Controller;

use Calendorama\View\TemplateRenderer;
use Symfony\Component\HttpFoundation\Response;

final readonly class IndexController
{
    public function __construct(private TemplateRenderer $templates)
    {
    }

    public function __invoke(): Response
    {
        return new Response(
            $this->templates->render('index.html.twig'),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
