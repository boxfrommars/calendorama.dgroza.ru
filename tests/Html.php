<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

final class Html
{
    public static function crawler(string $html): Crawler
    {
        return new Crawler($html);
    }

    public static function normalizedText(string $html): string
    {
        $crawler = self::crawler($html);
        foreach ($crawler->filter('.visually-hidden') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $text = $crawler->text('', true);
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        if ($normalized === null) {
            throw new RuntimeException('Could not normalize HTML text.');
        }

        return $normalized;
    }

    private function __construct()
    {
    }
}
