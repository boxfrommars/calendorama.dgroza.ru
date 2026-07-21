<?php

declare(strict_types=1);

use Calendorama\View\TemplateRenderer;
use Twig\Error\RuntimeError;

$runner->test('Twig rejects missing view-model values', static function (): void {
    $directory = sys_get_temp_dir() . '/calendorama-twig-' . bin2hex(random_bytes(8));
    mkdir($directory);

    try {
        file_put_contents($directory . '/strict.html.twig', '{{ missing }}');
        $templates = new TemplateRenderer($directory);

        try {
            $templates->render('strict.html.twig');
            throw new RuntimeException('Twig accepted an undefined variable');
        } catch (RuntimeError) {
            // Expected.
        }
    } finally {
        @unlink($directory . '/strict.html.twig');
        @rmdir($directory);
    }
});

$runner->test('Twig templates never bypass automatic escaping', static function (): void {
    foreach (glob(PROJECT_ROOT . '/templates/*.twig') ?: [] as $template) {
        $source = file_get_contents($template);
        Assert::true($source !== false, 'Could not read ' . basename($template));
        Assert::true(!str_contains($source, '|raw'), 'Unsafe raw filter in ' . basename($template));
        Assert::true(!str_contains($source, 'autoescape false'), 'Autoescape disabled in ' . basename($template));
    }
});
