<?php

declare(strict_types=1);

namespace Calendorama\Http;

use InvalidArgumentException;

final readonly class CalendarRequest
{
    private function __construct(
        public int $month,
        public int $year,
    ) {
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function fromArray(array $request): self
    {
        return new self(
            self::integer($request, 'month', 1, 12),
            self::integer($request, 'year', 1, 9999),
        );
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function integer(array $request, string $key, int $minimum, int $maximum): int
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
}
