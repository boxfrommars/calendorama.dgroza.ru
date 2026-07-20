<?php

declare(strict_types=1);

use Calendorama\Http\CalendarRequest;

$runner->test('request accepts valid strings and integers', static function (): void {
    $strings = CalendarRequest::fromArray(['month' => '3', 'year' => '2010']);
    Assert::same(3, $strings->month, 'String month failed');
    Assert::same(2010, $strings->year, 'String year failed');

    $integers = CalendarRequest::fromArray(['month' => 12, 'year' => 9999]);
    Assert::same(12, $integers->month, 'Integer month failed');
    Assert::same(9999, $integers->year, 'Integer year failed');
});

$runner->test('request rejects missing, malformed, and out-of-range values', static function (): void {
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
            CalendarRequest::fromArray($request);
            throw new RuntimeException('Invalid request was accepted: ' . json_encode($request));
        } catch (InvalidArgumentException) {
            // Expected.
        }
    }
});
