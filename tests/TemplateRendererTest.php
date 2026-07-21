<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\View\TemplateRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Error\RuntimeError;

final class TemplateRendererTest extends TestCase
{
    public function testRejectsMissingViewModelValues(): void
    {
        $directory = sys_get_temp_dir() . '/calendorama-twig-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        try {
            file_put_contents($directory . '/strict.html.twig', '{{ missing }}');
            $templates = new TemplateRenderer($directory);

            $this->expectException(RuntimeError::class);
            $templates->render('strict.html.twig');
        } finally {
            @unlink($directory . '/strict.html.twig');
            @rmdir($directory);
        }
    }

    public function testTemplatesNeverBypassAutomaticEscaping(): void
    {
        foreach (glob(dirname(__DIR__) . '/templates/*.twig') ?: [] as $template) {
            $source = file_get_contents($template);
            self::assertIsString($source);
            self::assertStringNotContainsString('|raw', $source);
            self::assertStringNotContainsString('autoescape false', $source);
        }
    }
}
