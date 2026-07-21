<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

use InvalidArgumentException;

final readonly class CalendarMonth
{
    public int $firstWeekday;
    public int $daysInMonth;
    public string $name;
    public int $previousMonth;
    public int $previousYear;
    public string $previousMonthName;
    public int $nextMonth;
    public int $nextYear;
    public string $nextMonthName;

    public function __construct(
        public int $month,
        public int $year,
    ) {
        if ($month < 1 || $month > 12 || $year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Invalid calendar date.');
        }

        $julianDay = gregoriantojd($month, 1, $year);
        $firstWeekday = (int) jddayofweek($julianDay);
        $this->firstWeekday = $firstWeekday === 0 ? 7 : $firstWeekday;
        $this->daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $this->name = jdmonthname($julianDay, CAL_MONTH_GREGORIAN_LONG);

        $this->previousMonth = $month > 1 ? $month - 1 : 12;
        $this->previousYear = $month > 1 ? $year : $year - 1;
        $this->previousMonthName = self::monthName($this->previousMonth);

        $this->nextMonth = $month < 12 ? $month + 1 : 1;
        $this->nextYear = $month < 12 ? $year : $year + 1;
        $this->nextMonthName = self::monthName($this->nextMonth);
    }

    private static function monthName(int $month): string
    {
        return jdmonthname(
            gregoriantojd($month, 1, 2000),
            CAL_MONTH_GREGORIAN_LONG,
        );
    }
}
