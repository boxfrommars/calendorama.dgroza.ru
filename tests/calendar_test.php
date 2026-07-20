<?php

declare(strict_types=1);

use Calendorama\Calendar\CalendarMonth;

$runner->test('calendar month calculates leap years and navigation', static function (): void {
    $february = new CalendarMonth(2, 2024);
    Assert::same(29, $february->daysInMonth, 'Leap February length is wrong');

    $january = new CalendarMonth(1, 2024);
    Assert::same(12, $january->previousMonth, 'January previous month is wrong');
    Assert::same(2023, $january->previousYear, 'January previous year is wrong');

    $december = new CalendarMonth(12, 2024);
    Assert::same(1, $december->nextMonth, 'December next month is wrong');
    Assert::same(2025, $december->nextYear, 'December next year is wrong');
});

$runner->test('calendar month supports accepted year extremes', static function (): void {
    Assert::same(1, (new CalendarMonth(1, 1))->year, 'Year 1 failed');
    Assert::same(9999, (new CalendarMonth(12, 9999))->year, 'Year 9999 failed');
});

$runner->test('calendar month rejects invalid dates', static function (): void {
    foreach ([[0, 2010], [13, 2010], [1, 0], [12, 10000]] as [$month, $year]) {
        try {
            new CalendarMonth($month, $year);
            throw new RuntimeException("Invalid date was accepted: {$month}/{$year}");
        } catch (InvalidArgumentException) {
            // Expected.
        }
    }
});
