<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/function.php';

try {
    [$monthNumber, $year] = parse_calendar_request($_REQUEST);
    $month = new Month($monthNumber, $year);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo 'Invalid calendar date.';
    exit;
}

$month->printCalendar();
$month->printMonthNav();
