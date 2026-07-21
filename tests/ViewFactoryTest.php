<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\View\AssetUrlGenerator;
use Calendorama\View\Model\CalendarCellView;
use Calendorama\View\TemplateRenderer;
use Calendorama\View\ViewFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewFactoryTest extends TestCase
{
    #[DataProvider('dates')]
    public function testBuildsCompleteIndexView(string $now, int $year, int $month, int $days): void
    {
        $view = self::factory($now)->index();

        self::assertSame('#d' . $year . '_' . $month, $view->initialRoute);
        self::assertSame($days, $view->daysInYear);
        self::assertSame(53, $view->postCount);
        self::assertCount(12, $view->months);
        self::assertSame('January', $view->months[0]->name);
        self::assertSame(5, $view->months[0]->postCount);
        self::assertSame('#d' . $year . '_1', $view->months[0]->route);
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

    public function testBuildsTypedSixWeekCalendarAndExplicitRoutes(): void
    {
        $view = self::factory('2026-07-21 12:00:00')->calendar(new CalendarMonth(3, 2010));

        self::assertCount(6, $view->weeks);
        self::assertCount(42, array_merge(...$view->weeks));
        self::assertSame(3, $view->month);
        self::assertSame(2010, $view->year);
        self::assertSame('March', $view->monthName);
        self::assertSame('#d2010_2', $view->previousRoute);
        self::assertSame('#d2010_4', $view->nextRoute);
        $firstPost = self::day($view->weeks, 1)->post;
        self::assertNotNull($firstPost);
        self::assertSame('#d2010_3_1', $firstPost->route);
        self::assertStringStartsWith('1.03.2010 — ', $firstPost->ariaLabel);
        self::assertFalse(property_exists(CalendarCellView::class, 'class'));

        $html = self::templates()->render('calendar-fragment.html.twig', ['calendar' => $view]);
        self::assertCount(42, Html::crawler($html)->filter('td'));
    }

    public function testUsesInjectedDateForTodayFlag(): void
    {
        $view = self::factory('2010-03-17 12:00:00')->calendar(new CalendarMonth(3, 2010));

        self::assertTrue(self::day($view->weeks, 17)->isToday);
        self::assertFalse(self::day($view->weeks, 16)->isToday);

        $html = self::templates()->render('calendar-fragment.html.twig', ['calendar' => $view]);
        self::assertCount(1, Html::crawler($html)->filter('td[aria-current="date"]'));
    }

    public function testHandlesLeapEmptyAndBoundaryMonths(): void
    {
        $factory = self::factory('2026-07-21 12:00:00');
        self::assertSame(29, self::day($factory->calendar(new CalendarMonth(2, 2024))->weeks, 29)->day);

        $empty = $factory->calendar(new CalendarMonth(10, 2026));
        foreach ($empty->weeks as $week) {
            foreach ($week as $cell) {
                self::assertNull($cell->post);
            }
        }

        $first = $factory->calendar(new CalendarMonth(1, 1));
        self::assertNull($first->previousRoute);
        self::assertSame('#d1_2', $first->nextRoute);

        $last = $factory->calendar(new CalendarMonth(12, 9999));
        self::assertSame('#d9999_11', $last->previousRoute);
        self::assertNull($last->nextRoute);

        $firstHtml = self::templates()->render('calendar-fragment.html.twig', ['calendar' => $first]);
        $lastHtml = self::templates()->render('calendar-fragment.html.twig', ['calendar' => $last]);
        self::assertCount(1, Html::crawler($firstHtml)->filter('[aria-label="Предыдущий месяц недоступен"][aria-disabled="true"]'));
        self::assertCount(1, Html::crawler($lastHtml)->filter('[aria-label="Следующий месяц недоступен"][aria-disabled="true"]'));
        self::assertStringNotContainsString('#d0_12', $firstHtml);
        self::assertStringNotContainsString('#d10000_1', $lastHtml);
    }

    public function testEscapesFileBackedTitleInTwig(): void
    {
        $manifest = tempnam(sys_get_temp_dir(), 'calendorama-');
        self::assertIsString($manifest);

        try {
            $json = json_encode(
                [['month' => 3, 'day' => 1, 'title' => 'a"b<c&d']],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            );
            self::assertNotFalse(file_put_contents($manifest, $json));
            $factory = self::factory('2026-07-21', new CalendarArchive($manifest));
            $html = self::templates()->render(
                'calendar-fragment.html.twig',
                ['calendar' => $factory->calendar(new CalendarMonth(3, 2010))],
            );

            self::assertStringContainsString('data-tooltip="a&quot;b&lt;c&amp;d"', $html);
            self::assertStringContainsString('alt="a&quot;b&lt;c&amp;d"', $html);
            self::assertStringContainsString('aria-label="1.03.2010 — a&quot;b&lt;c&amp;d"', $html);
        } finally {
            @unlink($manifest);
        }
    }

    private static function factory(string $now, ?CalendarArchive $archive = null): ViewFactory
    {
        $root = dirname(__DIR__);

        return new ViewFactory(
            $archive ?? new CalendarArchive($root . '/content/archive.json'),
            new DateTimeImmutable($now),
            new AssetUrlGenerator($root . '/public'),
        );
    }

    private static function templates(): TemplateRenderer
    {
        return new TemplateRenderer(dirname(__DIR__) . '/templates');
    }

    /** @param list<list<CalendarCellView>> $weeks */
    private static function day(array $weeks, int $day): CalendarCellView
    {
        foreach ($weeks as $week) {
            foreach ($week as $cell) {
                if ($cell->day === $day) {
                    return $cell;
                }
            }
        }

        self::fail('Day not found: ' . $day);
    }
}
