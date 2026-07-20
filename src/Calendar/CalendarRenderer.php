<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

final readonly class CalendarRenderer
{
    private const WEEKS = 6;

    public function __construct(private CalendarContent $content)
    {
    }

    public function renderCalendar(CalendarMonth $month): string
    {
        $calendar = '';
        $cell = 1;

        for ($week = 1; $week <= self::WEEKS; $week++) {
            $row = '';
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $row .= $this->renderDay($month, $cell);
                $cell++;
            }
            $calendar .= "\t<tr>" . $row . "</tr>\n";
        }

        return '<table id="calendar" cellspacing="1" class="'
            . $month->month . '_' . $month->year . '">'
            . $calendar . '</table>' . "\n";
    }

    public function renderMonthNav(CalendarMonth $month): string
    {
        return "\t<h2 id=\"month\">" . self::escape($month->name)
            . '&nbsp;&nbsp;<span id="month-nav" class="nav">'
            . '<a title="' . self::escape($month->previousMonthName . ' ' . $month->previousYear)
            . '" href="/#d' . $month->previousYear . '_' . $month->previousMonth
            . '" class="goto previous tooltip" id="m' . $month->previousYear . '_' . $month->previousMonth
            . '">&lt;</a><a title="' . self::escape($month->nextMonthName . ' ' . $month->nextYear)
            . '" href="/#d' . $month->nextYear . '_' . $month->nextMonth
            . '" class="goto next tooltip" id="m' . $month->nextYear . '_' . $month->nextMonth
            . '">&gt;</a></span></h2>' . "\n";
    }

    private function renderDay(CalendarMonth $month, int $cell): string
    {
        $content = ' ';
        $class = 'a-m-d';

        if ($cell >= $month->firstWeekday && $cell < $month->firstWeekday + $month->daysInMonth) {
            $day = $cell - $month->firstWeekday + 1;
            if ($this->content->hasPost($month->month, $day)) {
                $id = $month->month . '_' . $day;
                $title = self::escape($this->content->title($month->month, $day));
                $content = '<a id="f' . $month->year . '_' . $id . '" title="' . $title
                    . '" class="view_full tooltip goto" href="' . $this->content->fullImageUrl($month->month, $day) . '">'
                    . '<img alt="' . $title . '" class="thumb" width="90px" src="'
                    . $this->content->thumbnailUrl($month->month, $day) . '" /></a>';
                $class = '';
            } else {
                $content = '<span>' . $day . '</span>';
                $class = 'w-p';
            }

            if ($day === $month->today) {
                $class = trim($class . ' today');
            }
        }

        return self::tag('td', $content, ['class' => $class]);
    }

    /** @param array<string, string> $attributes */
    private static function tag(string $tag, string $content, array $attributes): string
    {
        $html = '<' . $tag;
        foreach ($attributes as $key => $value) {
            $html .= ' ' . self::escape($key) . '="' . self::escape($value) . '"';
        }

        return $html . '>' . $content . '</' . $tag . '>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
