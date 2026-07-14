<?php

declare(strict_types=1);

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function create_tag(string $tag, string $content = '', array $attributes = []): string
{
    $html = '<' . $tag;

    foreach ($attributes as $key => $value) {
        $html .= ' ' . escape_html((string) $key) . '="' . escape_html((string) $value) . '"';
    }

    return $html . '>' . $content . '</' . $tag . '>';
}

/**
 * @return array{0: int, 1: int}
 */
function parse_calendar_request(array $request): array
{
    $month = parse_calendar_integer($request, 'month', 1, 12);
    $year = parse_calendar_integer($request, 'year', 1, 9999);

    return [$month, $year];
}

function parse_calendar_integer(array $request, string $key, int $minimum, int $maximum): int
{
    if (!array_key_exists($key, $request)) {
        throw new InvalidArgumentException('Missing calendar parameter: ' . $key);
    }

    $value = $request[$key];
    if (is_int($value)) {
        $integer = $value;
    } elseif (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1) {
        $integer = (int) $value;
    } else {
        throw new InvalidArgumentException('Invalid calendar parameter: ' . $key);
    }

    if ($integer < $minimum || $integer > $maximum) {
        throw new InvalidArgumentException('Calendar parameter out of range: ' . $key);
    }

    return $integer;
}

final class Month
{
    private int $first;
    private int $total;
    private int $number;
    private string $name;
    private int $year;
    private int $lines = 6;
    private int $today;
    private int $prevMonth;
    private string $prevMonthName;
    private int $prevMonthYear;
    private int $nextMonth;
    private string $nextMonthName;
    private int $nextMonthYear;
    private int $current = 1;
    private string $calendar = '';

    /** @var array<int, string> */
    private array $titles = [];

    public function __construct(int $month, int $year)
    {
        if ($month < 1 || $month > 12 || $year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Invalid calendar date.');
        }

        $julianDay = gregoriantojd($month, 1, $year);
        $this->first = jddayofweek($julianDay);
        if ($this->first === 0) {
            $this->first = 7;
        }

        $this->total = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $this->number = $month;
        $this->year = $year;
        $this->today = (int) date('j');
        if ($month !== (int) date('n') || $year !== (int) date('Y')) {
            $this->today = 33;
        }

        $this->name = jdmonthname($julianDay, CAL_MONTH_GREGORIAN_LONG);
        $this->loadTitles();
        $this->setPreviousAndNextMonth();
    }

    private function setPreviousAndNextMonth(): void
    {
        if ($this->number > 1) {
            $this->prevMonth = $this->number - 1;
            $this->prevMonthYear = $this->year;
        } else {
            $this->prevMonth = 12;
            $this->prevMonthYear = $this->year - 1;
        }

        $this->prevMonthName = jdmonthname(
            gregoriantojd($this->prevMonth, 1, 2000),
            CAL_MONTH_GREGORIAN_LONG
        );

        if ($this->number < 12) {
            $this->nextMonth = $this->number + 1;
            $this->nextMonthYear = $this->year;
        } else {
            $this->nextMonth = 1;
            $this->nextMonthYear = $this->year + 1;
        }

        $this->nextMonthName = jdmonthname(
            gregoriantojd($this->nextMonth, 1, 2000),
            CAL_MONTH_GREGORIAN_LONG
        );
    }

    private function loadTitles(): void
    {
        $file = __DIR__ . '/blog/' . $this->number . '.txt';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $separator = strpos($line, ' ');
            if ($separator === false) {
                continue;
            }

            $day = (int) substr($line, 0, $separator);
            if ($day >= 1 && $day <= 31) {
                $this->titles[$day] = trim(substr($line, $separator + 1));
            }
        }
    }

    private function title(int $day): string
    {
        return $this->titles[$day] ?? 'no subject';
    }

    private function postExists(int $day): bool
    {
        return is_file(__DIR__ . '/blog/' . $this->number . '_' . $day . '_thmb.jpg');
    }

    public function printCalendar(): void
    {
        echo $this->renderCalendar();
    }

    public function renderCalendar(): string
    {
        $this->current = 1;
        $this->calendar = '';

        for ($line = 1; $line <= $this->lines; $line++) {
            $this->calendar .= $this->renderWeek();
        }

        return '<table id="calendar" cellspacing="1" class="'
            . $this->number . '_' . $this->year . '">'
            . $this->calendar . '</table>' . "\n";
    }

    private function renderWeek(): string
    {
        $week = '';
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $week .= $this->renderDay();
        }

        return "\t<tr>" . $week . "</tr>\n";
    }

    private function renderDay(): string
    {
        $content = ' ';
        $class = 'a-m-d';

        if ($this->current >= $this->first && $this->current < $this->first + $this->total) {
            $day = $this->current - $this->first + 1;
            if ($this->postExists($day)) {
                $id = $this->number . '_' . $day;
                $title = escape_html($this->title($day));
                $content = '<a id="f' . $this->year . '_' . $id . '" title="' . $title
                    . '" class="view_full tooltip goto" href="blog/' . $id . '_full.jpg">'
                    . '<img alt="' . $title . '" class="thumb" width="90px" src="blog/' . $id
                    . '_thmb.jpg" /></a>';
                $class = '';
            } else {
                $content = '<span>' . $day . '</span>';
                $class = 'w-p';
            }

            if ($day === $this->today) {
                $class = trim($class . ' today');
            }
        }

        $this->current++;

        return create_tag('td', $content, ['class' => $class]);
    }

    public function printMonthNav(): void
    {
        echo $this->renderMonthNav();
    }

    public function renderMonthNav(): string
    {
        return "\t<h2 id=\"month\">" . escape_html($this->name)
            . '&nbsp;&nbsp;<span id="month-nav" class="nav">'
            . '<a title="' . escape_html($this->prevMonthName . ' ' . $this->prevMonthYear)
            . '" href="/#d' . $this->prevMonthYear . '_' . $this->prevMonth
            . '" class="goto previous tooltip" id="m' . $this->prevMonthYear . '_' . $this->prevMonth
            . '">&lt;</a><a title="' . escape_html($this->nextMonthName . ' ' . $this->nextMonthYear)
            . '" href="/#d' . $this->nextMonthYear . '_' . $this->nextMonth
            . '" class="goto next tooltip" id="m' . $this->nextMonthYear . '_' . $this->nextMonth
            . '">&gt;</a></span></h2>' . "\n";
    }
}
