<?php

declare(strict_types=1);

error_reporting(E_ALL);

set_error_handler(
    static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

require_once dirname(__DIR__) . '/function.php';

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    $tests[$name] = $callback;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message . ': ' . $needle);
}

test('request parser accepts valid strings and integers', static function (): void {
    assert_true(parse_calendar_request(['month' => '3', 'year' => '2010']) === [3, 2010], 'String input failed');
    assert_true(parse_calendar_request(['month' => 12, 'year' => 9999]) === [12, 9999], 'Integer input failed');
});

test('request parser rejects malformed input', static function (): void {
    $invalidRequests = [
        [],
        ['month' => '0', 'year' => '2010'],
        ['month' => '13', 'year' => '2010'],
        ['month' => '3', 'year' => '0'],
        ['month' => '3', 'year' => '10000'],
        ['month' => 'March', 'year' => '2010'],
        ['month' => ['3'], 'year' => '2010'],
        ['month' => '3', 'year' => ['2010']],
    ];

    foreach ($invalidRequests as $request) {
        try {
            parse_calendar_request($request);
            throw new RuntimeException('Invalid request was accepted: ' . json_encode($request));
        } catch (InvalidArgumentException $exception) {
            // Expected.
        }
    }
});

test('March 2010 preserves posts and navigation', static function (): void {
    $month = new Month(3, 2010);
    $html = $month->renderCalendar() . $month->renderMonthNav();
    assert_contains('class="3_2010"', $html, 'Calendar identity missing');
    assert_contains('<h2 id="month">March&nbsp;&nbsp;', $html, 'Full month name missing');
    assert_contains('blog/3_1_full.jpg', $html, 'Known post missing');
    assert_contains('id="m2010_2"', $html, 'Previous month missing');
    assert_contains('id="m2010_4"', $html, 'Next month missing');
});

test('year boundaries are correct', static function (): void {
    $january = (new Month(1, 2024))->renderMonthNav();
    $december = (new Month(12, 2024))->renderMonthNav();
    assert_contains('id="m2023_12"', $january, 'January previous month is wrong');
    assert_contains('id="m2025_1"', $december, 'December next month is wrong');
});

test('supported year extremes render without warnings', static function (): void {
    $first = (new Month(1, 1))->renderCalendar() . (new Month(1, 1))->renderMonthNav();
    $last = (new Month(12, 9999))->renderCalendar() . (new Month(12, 9999))->renderMonthNav();
    assert_contains('class="1_1"', $first, 'Year 1 did not render');
    assert_contains('class="12_9999"', $last, 'Year 9999 did not render');
});

test('leap February contains day 29', static function (): void {
    $html = (new Month(2, 2024))->renderCalendar();
    assert_contains('<span>29</span>', $html, 'Leap day missing');
});

test('month without posts renders safely', static function (): void {
    $html = (new Month(10, 2026))->renderCalendar();
    assert_contains('class="10_2026"', $html, 'Empty month did not render');
    assert_true(!str_contains($html, '<a id="f2026_10_'), 'Unexpected post in empty month');
});

test('HTML escaping covers attribute-sensitive characters', static function (): void {
    assert_true(escape_html('a"b<c&d') === 'a&quot;b&lt;c&amp;d', 'HTML escaping failed');
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        echo "PASS: {$name}\n";
    } catch (Throwable $throwable) {
        $failures++;
        fwrite(STDERR, "FAIL: {$name}: {$throwable->getMessage()}\n");
    }
}

restore_error_handler();

if ($failures > 0) {
    exit(1);
}

echo 'All tests passed (' . count($tests) . ").\n";
