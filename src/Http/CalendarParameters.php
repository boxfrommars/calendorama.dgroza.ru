<?php

declare(strict_types=1);

namespace Calendorama\Http;

use InvalidArgumentException;

final readonly class CalendarParameters
{
    private function __construct(
        public int $month,
        public int $year,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public static function fromArray(array $parameters): self
    {
        return new self(
            self::integer($parameters, 'month', 1, 12),
            self::integer($parameters, 'year', 1, 9999),
        );
    }

    /** @param array<string, mixed> $parameters */
    private static function integer(array $parameters, string $key, int $minimum, int $maximum): int
    {
        if (!array_key_exists($key, $parameters)) {
            throw new InvalidArgumentException('Missing calendar parameter: ' . $key);
        }

        $value = $parameters[$key];
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
}
