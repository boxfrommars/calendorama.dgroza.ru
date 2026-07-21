<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

final readonly class CalendarMonth
{
    public int $firstWeekday;
    public int $daysInMonth;
    public string $name;

    public function __construct(
        public int $month,
        public int $year,
    ) {
        if ($month < 1 || $month > 12 || $year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Invalid calendar date.');
        }

        $firstDay = DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%04d-%d-1', $year, $month),
            new DateTimeZone('UTC'),
        );
        if ($firstDay === false) {
            throw new LogicException('Unable to construct a validated calendar month.');
        }

        $this->firstWeekday = (int) $firstDay->format('N');
        $this->daysInMonth = (int) $firstDay->format('t');
        $this->name = $firstDay->format('F');
    }

    public function previous(): ?self
    {
        if ($this->month === 1 && $this->year === 1) {
            return null;
        }

        return new self(
            $this->month === 1 ? 12 : $this->month - 1,
            $this->month === 1 ? $this->year - 1 : $this->year,
        );
    }

    public function next(): ?self
    {
        if ($this->month === 12 && $this->year === 9999) {
            return null;
        }

        return new self(
            $this->month === 12 ? 1 : $this->month + 1,
            $this->month === 12 ? $this->year + 1 : $this->year,
        );
    }
}
