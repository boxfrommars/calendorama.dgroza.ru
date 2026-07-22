<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Rss\RssGenerator;
use Calendorama\View\TemplateRenderer;
use DOMDocument;
use DOMNode;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RssTest extends TestCase
{
    public function testCommittedFeedMatchesGeneratorExactly(): void
    {
        self::assertSame(self::generator()->render(), self::source());
    }

    public function testFeedUsesManifestDataAndKeepsLegacyGuids(): void
    {
        $source = self::source();
        $xpath = self::xpath($source);

        $channelDates = self::values($xpath, '/rss/channel/pubDate');
        self::assertSame(['Fri, 5 Mar 2010 06:00:00 GMT'], $channelDates);

        $links = self::values($xpath, '/rss/channel/link | /rss/channel/atom:link/@href | /rss/channel/item/link');
        self::assertCount(55, $links);
        foreach ($links as $link) {
            self::assertStringStartsWith('https://calendorama.dgroza.ru', $link);
        }

        $itemLinks = self::values($xpath, '/rss/channel/item/link');
        self::assertCount(53, $itemLinks);
        foreach ($itemLinks as $link) {
            self::assertMatchesRegularExpression('~^https://calendorama\.dgroza\.ru/#d[0-9]{4}_[0-9]{1,2}_[0-9]{1,2}$~D', $link);
        }
        self::assertContains('https://calendorama.dgroza.ru/#d2009_3_25', $itemLinks);

        $descriptions = self::values($xpath, '/rss/channel/item/description');
        self::assertCount(53, $descriptions);
        foreach ($descriptions as $description) {
            $matched = preg_match('~<img src="https://calendorama\.dgroza\.ru/blog/([0-9]+_[0-9]+_full\.jpg)" />~D', $description, $match);
            self::assertSame(1, $matched);
            self::assertFileExists(dirname(__DIR__) . '/public/blog/' . $match[1]);
        }

        $guids = self::values($xpath, '/rss/channel/item/guid');
        self::assertCount(53, $guids);
        $newGuid = 'http://calendorama.dgroza.ru/blog/3_25_thmb.jpg';
        self::assertContains($newGuid, $guids);
        $existingGuids = array_values(array_filter(
            $guids,
            static fn (string $guid): bool => $guid !== $newGuid,
        ));
        self::assertCount(52, $existingGuids);
        self::assertSame(
            'c1f240e4a17aba4aed0b5abc53d754c8a5818efd540565db637fb4d40e5c4b93',
            hash('sha256', implode("\n", $existingGuids)),
        );
        foreach ($guids as $guid) {
            self::assertStringStartsWith('http://calendorama.dgroza.ru/blog/', $guid);
        }

        $titles = self::values($xpath, '/rss/channel/item/title');
        self::assertContains('б-ка: поезда', $titles);
        self::assertContains('поивт (2 курс): математика', $titles);
        self::assertContains('б-ка: тадам!', $titles);
        self::assertNotContains('мб-ка: поезда', $titles);
        self::assertNotContains('ПОиВТ (2 курс): математика', $titles);
        self::assertNotContains('б-ка: тадам', $titles);

        $publicationDates = array_map(
            static fn (string $date): int => (new \DateTimeImmutable($date))->getTimestamp(),
            self::values($xpath, '/rss/channel/item/pubDate'),
        );
        $sortedDates = $publicationDates;
        rsort($sortedDates);
        self::assertSame($sortedDates, $publicationDates);

        self::assertSame(53, substr_count($source, '<![CDATA['));
        self::assertStringNotContainsString('calendorama.dgroza.ruf', $source);
        self::assertStringNotContainsString('/#2009_', $source);
    }

    public function testFeedEscapesManifestTitles(): void
    {
        $manifest = self::temporaryManifest([[
            'month' => 3,
            'day' => 1,
            'title' => 'a"b<c&d',
            'publishedAt' => '2009-03-01T04:00:00Z',
        ]]);

        try {
            $source = self::generator(new CalendarArchive($manifest))->render();
            self::assertStringContainsString('<title>a&quot;b&lt;c&amp;d</title>', $source);
            self::assertSame(['a"b<c&d'], self::values(self::xpath($source), '/rss/channel/item/title'));
        } finally {
            @unlink($manifest);
        }
    }

    public function testRejectsAnEmptyArchive(): void
    {
        $manifest = self::temporaryManifest([]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('empty archive');
            self::generator(new CalendarArchive($manifest))->render();
        } finally {
            @unlink($manifest);
        }
    }

    private static function generator(?CalendarArchive $archive = null): RssGenerator
    {
        $root = dirname(__DIR__);

        return new RssGenerator(
            $archive ?? new CalendarArchive($root . '/content/archive.json'),
            new TemplateRenderer($root . '/templates'),
        );
    }

    private static function source(): string
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/rss.xml');
        self::assertIsString($source);

        return $source;
    }

    private static function xpath(string $source): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($source));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');

        return $xpath;
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

    /** @param list<array<string, mixed>> $records */
    private static function temporaryManifest(array $records): string
    {
        $file = tempnam(sys_get_temp_dir(), 'calendorama-rss-');
        self::assertIsString($file);
        $json = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        self::assertNotFalse(file_put_contents($file, $json));

        return $file;
    }
}
