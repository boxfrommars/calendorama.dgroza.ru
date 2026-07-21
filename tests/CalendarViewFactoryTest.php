<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarContent;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\View\CalendarViewFactory;
use Calendorama\View\Model\CalendarCellView;
use Calendorama\View\TemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CalendarViewFactoryTest extends TestCase
{
    public function testBuildsTypedSixWeekViewAndPreservesHtml(): void
    {
        $factory = self::factory('2026-07-21 12:00:00');
        $view = $factory->create(new CalendarMonth(3, 2010));

        self::assertCount(6, $view->weeks);
        self::assertCount(42, array_merge(...$view->weeks));
        self::assertSame('3_2010', $view->class);
        self::assertSame('m2010_2', $view->navigation->previous->id);
        self::assertSame('m2010_4', $view->navigation->next->id);

        $html = self::templates()->render('calendar-fragment.html.twig', ['calendar' => $view]);
        self::assertSame(
            'edda0add5890f658f5881e190149fa77b4c15ebf32bd3cca8d02970ff3afd348',
            hash('sha256', Html::canonical($html)),
        );
    }

    public function testUsesInjectedClockForTodayClass(): void
    {
        $view = self::factory('2010-03-17 12:00:00')->create(new CalendarMonth(3, 2010));

        self::assertSame('today', self::day($view->weeks, 17)->class);
        self::assertStringNotContainsString('today', self::day($view->weeks, 16)->class);
    }

    public function testHandlesLeapEmptyAndBoundaryMonths(): void
    {
        $factory = self::factory('2026-07-21 12:00:00');
        self::assertSame(29, self::day($factory->create(new CalendarMonth(2, 2024))->weeks, 29)->day);

        $empty = $factory->create(new CalendarMonth(10, 2026));
        foreach ($empty->weeks as $week) {
            foreach ($week as $cell) {
                self::assertNull($cell->post);
            }
        }

        self::assertSame('m0_12', $factory->create(new CalendarMonth(1, 1))->navigation->previous->id);
        self::assertSame('m10000_1', $factory->create(new CalendarMonth(12, 9999))->navigation->next->id);
    }

    public function testEscapesFileBackedTitleInTwig(): void
    {
        $directory = sys_get_temp_dir() . '/calendorama-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        try {
            file_put_contents($directory . '/3.txt', "1 a\"b<c&d\n");
            touch($directory . '/3_1_thmb.jpg');
            $factory = new CalendarViewFactory(new CalendarContent($directory), new MockClock('2026-07-21'));
            $html = self::templates()->render(
                'calendar-fragment.html.twig',
                ['calendar' => $factory->create(new CalendarMonth(3, 2010))],
            );

            self::assertStringContainsString('title="a&quot;b&lt;c&amp;d"', $html);
            self::assertStringContainsString('alt="a&quot;b&lt;c&amp;d"', $html);
        } finally {
            @unlink($directory . '/3.txt');
            @unlink($directory . '/3_1_thmb.jpg');
            @rmdir($directory);
        }
    }

    private static function factory(string $now): CalendarViewFactory
    {
        return new CalendarViewFactory(
            new CalendarContent(dirname(__DIR__) . '/public/blog'),
            new MockClock($now),
        );
    }

    private static function templates(): TemplateRenderer
    {
        return new TemplateRenderer(dirname(__DIR__) . '/templates');
    }

    /**
     * @param list<list<CalendarCellView>> $weeks
     */
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
