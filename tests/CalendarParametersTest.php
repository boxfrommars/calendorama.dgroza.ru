<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Http\CalendarParameters;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarParametersTest extends TestCase
{
    /**
     * @param array<string, int|string> $input
     */
    #[DataProvider('validParameters')]
    public function testAcceptsValidParameters(array $input, int $month, int $year): void
    {
        $parameters = CalendarParameters::fromArray($input);

        self::assertSame($month, $parameters->month);
        self::assertSame($year, $parameters->year);
    }

    /** @return iterable<string, array{array<string, int|string>, int, int}> */
    public static function validParameters(): iterable
    {
        yield 'numeric strings' => [['month' => '3', 'year' => '2010'], 3, 2010];
        yield 'integers' => [['month' => 12, 'year' => 9999], 12, 9999];
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('invalidParameters')]
    public function testRejectsInvalidParameters(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarParameters::fromArray($input);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidParameters(): iterable
    {
        yield 'missing' => [[]];
        yield 'month below range' => [['month' => '0', 'year' => '2010']];
        yield 'month above range' => [['month' => '13', 'year' => '2010']];
        yield 'year below range' => [['month' => '3', 'year' => '0']];
        yield 'year above range' => [['month' => '3', 'year' => '10000']];
        yield 'month text' => [['month' => 'March', 'year' => '2010']];
        yield 'month array' => [['month' => ['3'], 'year' => '2010']];
        yield 'year array' => [['month' => '3', 'year' => ['2010']]];
    }
}
