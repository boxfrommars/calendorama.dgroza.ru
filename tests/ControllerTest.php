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
    public function testIndexResponsePreservesHtmlContract(): void
    {
        $response = self::application()->index();
        $html = self::content($response);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame(
            '3d252a5ba8616a4d076b12d2049392368e0c003b7a82a6b5803cd87d85194f47',
            hash('sha256', Html::canonical($html)),
        );
    }

    public function testCalendarResponsePreservesHtmlContract(): void
    {
        $request = Request::create('/handler.php', 'POST', ['month' => '3', 'year' => '2010']);
        $response = self::application()->calendar($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame(
            'edda0add5890f658f5881e190149fa77b4c15ebf32bd3cca8d02970ff3afd348',
            hash('sha256', Html::canonical(self::content($response))),
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
