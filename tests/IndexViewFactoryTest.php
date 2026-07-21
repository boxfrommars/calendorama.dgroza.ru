<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\View\AssetUrlGenerator;
use Calendorama\View\IndexViewFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class IndexViewFactoryTest extends TestCase
{
    #[DataProvider('dates')]
    public function testBuildsCompleteIndexView(string $now, int $year, int $month, int $days): void
    {
        $view = self::factory($now)->create();

        self::assertSame($year, $view->currentYear);
        self::assertSame($month, $view->currentMonth);
        self::assertSame('#d' . $year . '_' . $month, $view->initialRoute);
        self::assertSame($days, $view->daysInYear);
        self::assertSame(53, $view->postCount);
        self::assertCount(12, $view->months);
        self::assertSame('January', $view->months[0]->name);
        self::assertSame(5, $view->months[0]->postCount);
        self::assertSame('/#d' . $year . '_1', $view->months[0]->href);
        self::assertSame('#d' . $year . '_12', $view->months[11]->route);
        self::assertMatchesRegularExpression('~^/style\.css\?v=[a-f0-9]{12}$~D', $view->styleUrl);
        self::assertMatchesRegularExpression('~^/script\.js\?v=[a-f0-9]{12}$~D', $view->scriptUrl);
    }

    /** @return iterable<string, array{string, int, int, int}> */
    public static function dates(): iterable
    {
        yield 'ordinary year' => ['2026-07-21T12:00:00+00:00', 2026, 7, 365];
        yield 'leap year' => ['2028-02-29T12:00:00+00:00', 2028, 2, 366];
    }

    private static function factory(string $now): IndexViewFactory
    {
        $root = dirname(__DIR__);

        return new IndexViewFactory(
            new CalendarArchive($root . '/content/archive.json'),
            new MockClock($now),
            new AssetUrlGenerator($root . '/public'),
        );
    }
}
