<?php

declare(strict_types=1);

use Calendorama\Calendar\CalendarContent;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\Calendar\CalendarRenderer;

$renderer = new CalendarRenderer(new CalendarContent(PROJECT_ROOT . '/public/blog'));

$runner->test('March 2010 preserves the complete HTML contract', static function () use ($renderer): void {
    $month = new CalendarMonth(3, 2010);
    $html = $renderer->renderCalendar($month) . $renderer->renderMonthNav($month);

    Assert::same(
        'd44e2d0516fefa7735f33baa014fa650202c7b3051ddf54ed4a5a1563ce542ed',
        hash('sha256', $html),
        'March 2010 HTML snapshot changed',
    );
    Assert::contains('class="3_2010"', $html, 'Calendar identity missing');
    Assert::contains('blog/3_1_full.jpg', $html, 'Known post missing');
    Assert::contains('id="m2010_2"', $html, 'Previous month missing');
    Assert::contains('id="m2010_4"', $html, 'Next month missing');
});

$runner->test('renderer handles leap, empty, and boundary months', static function () use ($renderer): void {
    $leap = $renderer->renderCalendar(new CalendarMonth(2, 2024));
    Assert::contains('<span>29</span>', $leap, 'Leap day missing');

    $empty = $renderer->renderCalendar(new CalendarMonth(10, 2026));
    Assert::contains('class="10_2026"', $empty, 'Empty month did not render');
    Assert::true(!str_contains($empty, '<a id="f2026_10_'), 'Unexpected post in empty month');

    $first = $renderer->renderMonthNav(new CalendarMonth(1, 1));
    $last = $renderer->renderMonthNav(new CalendarMonth(12, 9999));
    Assert::contains('id="m0_12"', $first, 'Year 1 previous navigation changed');
    Assert::contains('id="m10000_1"', $last, 'Year 9999 next navigation changed');
});

$runner->test('renderer escapes file-backed titles at the HTML boundary', static function (): void {
    $directory = sys_get_temp_dir() . '/calendorama-' . bin2hex(random_bytes(8));
    mkdir($directory);

    try {
        file_put_contents($directory . '/3.txt', "1 a\"b<c&d\n");
        touch($directory . '/3_1_thmb.jpg');

        $renderer = new CalendarRenderer(new CalendarContent($directory));
        $html = $renderer->renderCalendar(new CalendarMonth(3, 2010));
        Assert::contains('title="a&quot;b&lt;c&amp;d"', $html, 'Title was not escaped');
        Assert::contains('alt="a&quot;b&lt;c&amp;d"', $html, 'Image alt was not escaped');
    } finally {
        @unlink($directory . '/3.txt');
        @unlink($directory . '/3_1_thmb.jpg');
        @rmdir($directory);
    }
});
