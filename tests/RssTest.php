<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use DOMDocument;
use DOMNode;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class RssTest extends TestCase
{
    public function testFeedUsesValidHttpsLinksAndKeepsLegacyGuids(): void
    {
        $file = dirname(__DIR__) . '/public/rss.xml';
        $source = file_get_contents($file);
        self::assertIsString($source);

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($source));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        $links = self::values($xpath, '/rss/channel/link | /rss/channel/atom:link/@href | /rss/channel/item/link');
        self::assertCount(54, $links);
        foreach ($links as $link) {
            self::assertStringStartsWith('https://calendorama.dgroza.ru', $link);
        }

        $itemLinks = self::values($xpath, '/rss/channel/item/link');
        self::assertCount(52, $itemLinks);
        foreach ($itemLinks as $link) {
            self::assertMatchesRegularExpression('~^https://calendorama\.dgroza\.ru/#d[0-9]{4}_[0-9]{1,2}_[0-9]{1,2}$~D', $link);
        }

        $descriptions = self::values($xpath, '/rss/channel/item/description');
        self::assertCount(52, $descriptions);
        foreach ($descriptions as $description) {
            $matched = preg_match('~<img src="https://calendorama\.dgroza\.ru/blog/([0-9]+_[0-9]+_full\.jpg)" />~D', $description, $match);
            self::assertSame(1, $matched);
            self::assertFileExists(dirname(__DIR__) . '/public/blog/' . $match[1]);
        }

        $guids = self::values($xpath, '/rss/channel/item/guid');
        self::assertCount(52, $guids);
        self::assertSame(
            'c1f240e4a17aba4aed0b5abc53d754c8a5818efd540565db637fb4d40e5c4b93',
            hash('sha256', implode("\n", $guids)),
        );
        foreach ($guids as $guid) {
            self::assertStringStartsWith('http://calendorama.dgroza.ru/blog/', $guid);
        }

        self::assertStringNotContainsString('calendorama.dgroza.ruf', $source);
        self::assertStringNotContainsString('/#2009_', $source);
    }

    /** @return list<string> */
    private static function values(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false) {
            self::fail('Invalid XPath expression: ' . $expression);
        }

        $values = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode) {
                $values[] = trim($node->nodeValue ?? '');
            }
        }

        return $values;
    }
}
