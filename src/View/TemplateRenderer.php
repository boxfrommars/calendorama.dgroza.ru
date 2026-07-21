<?php

declare(strict_types=1);

namespace Calendorama\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final readonly class TemplateRenderer
{
    private Environment $twig;

    public function __construct(string $templateDirectory)
    {
        $this->twig = new Environment(
            new FilesystemLoader($templateDirectory),
            [
                'autoescape' => 'html',
                'cache' => false,
                'strict_variables' => true,
                'use_yield' => true,
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
