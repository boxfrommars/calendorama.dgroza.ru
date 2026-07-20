<?php

declare(strict_types=1);

use Calendorama\Calendar\CalendarContent;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\Calendar\CalendarRenderer;
use Calendorama\Http\CalendarRequest;

header('Content-Type: text/html; charset=utf-8');

require_once dirname(__DIR__) . '/src/bootstrap.php';

try {
    $request = CalendarRequest::fromArray($_POST);
    $month = new CalendarMonth($request->month, $request->year);
} catch (InvalidArgumentException) {
    http_response_code(400);
    echo 'Invalid calendar date.';
    exit;
}

$content = new CalendarContent(__DIR__ . '/blog');
$renderer = new CalendarRenderer($content);

echo $renderer->renderCalendar($month);
echo $renderer->renderMonthNav($month);
