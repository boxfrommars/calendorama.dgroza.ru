<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarMonth;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarMonthTest extends TestCase
{
    public function testCalculatesLeapYearAndNavigation(): void
    {
        self::assertSame(29, (new CalendarMonth(2, 2024))->daysInMonth);
        $march2010 = new CalendarMonth(3, 2010);
        self::assertSame(1, $march2010->firstWeekday);
        self::assertSame(31, $march2010->daysInMonth);
        self::assertSame('March', $march2010->name);

        $january = new CalendarMonth(1, 2024);
        $previous = $january->previous();
        self::assertNotNull($previous);
        self::assertSame(12, $previous->month);
        self::assertSame(2023, $previous->year);

        $december = new CalendarMonth(12, 2024);
        $next = $december->next();
        self::assertNotNull($next);
        self::assertSame(1, $next->month);
        self::assertSame(2025, $next->year);
    }

    public function testSupportsAcceptedYearExtremes(): void
    {
        self::assertSame(1, (new CalendarMonth(1, 1))->year);
        self::assertSame(9999, (new CalendarMonth(12, 9999))->year);
        self::assertNull((new CalendarMonth(1, 1))->previous());
        self::assertNull((new CalendarMonth(12, 9999))->next());
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidDates(int $month, int $year): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CalendarMonth($month, $year);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidDates(): iterable
    {
        yield 'month below range' => [0, 2010];
        yield 'month above range' => [13, 2010];
        yield 'year below range' => [1, 0];
        yield 'year above range' => [12, 10000];
    }
}
