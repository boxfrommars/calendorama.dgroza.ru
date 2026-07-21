<?php

declare(strict_types=1);

use Calendorama\Calendar\CalendarContent;
use Calendorama\Calendar\CalendarMonth;
use Calendorama\Calendar\CalendarRenderer;
use Calendorama\Http\CalendarRequest;
use Calendorama\View\TemplateRenderer;

header('Content-Type: text/html; charset=utf-8');

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    $request = CalendarRequest::fromArray($_POST);
    $month = new CalendarMonth($request->month, $request->year);
} catch (InvalidArgumentException) {
    http_response_code(400);
    echo 'Invalid calendar date.';
    exit;
}

$content = new CalendarContent(__DIR__ . '/blog');
$templates = new TemplateRenderer(dirname(__DIR__) . '/templates');
$renderer = new CalendarRenderer($content, $templates);

echo $renderer->render($month);
