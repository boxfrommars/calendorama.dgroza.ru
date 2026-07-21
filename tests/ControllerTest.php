<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\ApplicationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ControllerTest extends TestCase
{
    public function testIndexResponseHasSemanticLandmarksAndPermanentDialogs(): void
    {
        $response = self::application()->index();
        $html = self::content($response);
        $crawler = Html::crawler($html);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertCount(1, $crawler->filter('html[lang="ru"]'));
        self::assertCount(1, $crawler->filter('header'));
        self::assertCount(1, $crawler->filter('nav'));
        self::assertCount(1, $crawler->filter('main'));
        self::assertCount(1, $crawler->filter('footer'));
        self::assertCount(3, $crawler->filter('button[type="button"][data-panel-target]'));
        self::assertCount(3, $crawler->filter('dialog[data-panel][data-close-on-click]'));
        self::assertCount(1, $crawler->filter('dialog[data-region="post-view"][data-close-on-click] img'));
        self::assertCount(1, $crawler->filter('[data-region="status"][role="status"][hidden]'));
        self::assertCount(1, $crawler->filter('[data-region="alert"][role="alert"][hidden]'));
        self::assertCount(1, $crawler->filter('[data-region="tooltip"][role="tooltip"][hidden]'));
        self::assertSame('/style.css?i=3', $crawler->filter('link[rel="stylesheet"]')->attr('href'));
        self::assertSame('/script.js?i=5', $crawler->filter('script[type="module"]')->attr('src'));
        self::assertCount(1, $crawler->filter('script[src]'));
        self::assertStringNotContainsString('jquery', strtolower($html));
        self::assertStringNotContainsString('history.js', strtolower($html));
        self::assertStringNotContainsString('options.js', strtolower($html));
    }

    public function testIndexRoutesAndAuthorMarkupAreExplicit(): void
    {
        $crawler = Html::crawler(self::content(self::application()->index()));
        $monthLinks = $crawler->filter('dialog[data-panel="montheslist"] a[data-calendar-route]');

        self::assertCount(12, $monthLinks);
        self::assertSame('/#d2010_1', $monthLinks->first()->attr('href'));
        self::assertSame('#d2010_1', $monthLinks->first()->attr('data-calendar-route'));
        self::assertCount(31, $crawler->filter('.author-entry'));
        self::assertCount(31, $crawler->filter('.author-entry > dt'));
        self::assertCount(31, $crawler->filter('.author-entry > dd'));
        self::assertCount(0, $crawler->filter('dl > br'));
        self::assertCount(0, $crawler->filter('.goto, .pseudo, .cl, .cleanable, .tooltip, .permtooltip, .pseudotooltip'));
        self::assertCount(0, $crawler->filter('a[id]'));
    }

    public function testIndexVisibleTextIsUnchanged(): void
    {
        self::assertSame(
            'efe1acf7e4a14cc1230b47bf766f42c8db46aebc050503407576b03233c78538',
            hash('sha256', Html::normalizedText(self::content(self::application()->index()))),
        );
    }

    public function testCalendarResponseHasSemanticDomContract(): void
    {
        $request = Request::create('/handler.php', 'POST', ['month' => '3', 'year' => '2010']);
        $response = self::application()->calendar($request);
        $html = self::content($response);
        $crawler = Html::crawler($html);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertCount(1, $crawler->filter('section[data-calendar-view][data-month="3"][data-year="2010"]'));
        self::assertCount(1, $crawler->filter('table.calendar[aria-label="March 2010"] > tbody'));
        self::assertCount(6, $crawler->filter('table.calendar > tbody > tr'));
        self::assertCount(42, $crawler->filter('table.calendar > tbody > tr > td'));
        self::assertGreaterThan(0, $crawler->filter('td[aria-hidden="true"]')->count());
        self::assertSame('', $crawler->filter('td[aria-hidden="true"]')->first()->text());
        self::assertCount(27, $crawler->filter('a.view_full[data-calendar-route]'));
        self::assertSame('blog/3_1_full.jpg', $crawler->filter('a[data-calendar-route="#d2010_3_1"]')->attr('href'));
        self::assertSame('90', $crawler->filter('a[data-calendar-route="#d2010_3_1"] img')->attr('width'));
        self::assertCount(2, $crawler->filter('.month-nav a[data-calendar-route]'));
        self::assertCount(0, $crawler->filter('[id], .goto, .tooltip'));
        self::assertStringNotContainsString('cellspacing', $html);
    }

    public function testCalendarVisibleTextIsUnchanged(): void
    {
        $response = self::application()->calendar(
            Request::create('/handler.php', 'POST', ['month' => '3', 'year' => '2010']),
        );

        self::assertSame(
            '274aa900824072c6bc7f33264c770fe24eb76c5a7ca483bd08b02a4b17b9644d',
            hash('sha256', Html::normalizedText(self::content($response))),
        );
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('invalidRequests')]
    public function testInvalidCalendarRequestReturnsExactError(array $parameters, string $method): void
    {
        $request = Request::create('/handler.php', $method, $parameters);
        $response = self::application()->calendar($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('Invalid calendar date.', self::content($response));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'GET without parameters stays 400' => [[], 'GET'];
        yield 'POST without parameters' => [[], 'POST'];
        yield 'string value' => [['month' => 'March', 'year' => '2010'], 'POST'];
        yield 'array value' => [['month' => ['3'], 'year' => '2010'], 'POST'];
        yield 'month out of range' => [['month' => '13', 'year' => '2010'], 'POST'];
        yield 'year out of range' => [['month' => '3', 'year' => '10000'], 'POST'];
    }

    private static function application(): \Calendorama\Application
    {
        return ApplicationFactory::create(dirname(__DIR__));
    }

    private static function content(Response $response): string
    {
        $content = $response->getContent();
        if (!is_string($content)) {
            self::fail('Response content is not a string.');
        }

        return $content;
    }
}
