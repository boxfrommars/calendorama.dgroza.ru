<?php

declare(strict_types=1);

final class TestRunner
{
    /** @var array<string, callable(): void> */
    private array $tests = [];

    public function test(string $name, callable $callback): void
    {
        $this->tests[$name] = $callback;
    }

    public function run(): int
    {
        $failures = 0;

        foreach ($this->tests as $name => $callback) {
            try {
                $callback();
                echo "PASS: {$name}\n";
            } catch (Throwable $throwable) {
                $failures++;
                fwrite(STDERR, "FAIL: {$name}: {$throwable->getMessage()}\n");
            }
        }

        if ($failures === 0) {
            echo 'All tests passed (' . count($this->tests) . ").\n";
        }

        return $failures;
    }
}

final class Assert
{
    public static function true(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                $message . '; expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true),
            );
        }
    }

    public static function contains(string $needle, string $haystack, string $message): void
    {
        self::true(str_contains($haystack, $needle), $message . ': ' . $needle);
    }

    public static function canonicalHtml(string $html): string
    {
        $normalized = str_replace("\r\n", "\n", trim($html));
        $canonical = preg_replace('/>\s+</u', '><', $normalized);

        if ($canonical === null) {
            throw new RuntimeException('Could not canonicalize HTML.');
        }

        return $canonical;
    }
}
