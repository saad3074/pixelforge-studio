<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact USD amounts as integer nanodollars (1 USD = 1_000_000_000).
 * Pixelforge bills to 9 decimal places; nanodollars represent every cost exactly.
 */
final class Money
{
    public const SCALE = 1_000_000_000;

    public static function fromDecimalString(string $amount): int
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '+-');

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 9, '0'), 0, 9);

        if (strlen($fraction) > 9 && rtrim(substr($fraction, 9), '0') !== '') {
            throw new InvalidArgumentException("Amount has more than 9 decimal places: {$amount}");
        }

        $nanos = ((int) $whole * self::SCALE) + (int) $fraction;

        return $negative ? -$nanos : $nanos;
    }

    public static function toDecimalString(int $nanodollars, int $decimals = 9): string
    {
        $negative = $nanodollars < 0;
        $nanodollars = abs($nanodollars);

        $whole = intdiv($nanodollars, self::SCALE);
        $fraction = str_pad((string) ($nanodollars % self::SCALE), 9, '0', STR_PAD_LEFT);

        if ($decimals < 9) {
            $fraction = substr($fraction, 0, $decimals);
        } elseif ($decimals > 9) {
            $fraction = str_pad($fraction, $decimals, '0');
        }

        $value = $decimals === 0
            ? (string) $whole
            : sprintf('%d.%s', $whole, $fraction);

        return $negative ? "-{$value}" : $value;
    }

    public static function formatDisplay(int $nanodollars, int $decimals = 4): string
    {
        return '$'.self::toDecimalString($nanodollars, $decimals);
    }

    /**
     * Cost for a token count at a USD-per-1M-tokens rate (exact decimal string).
     */
    public static function costForTokens(int $tokens, string $ratePerMillion): int
    {
        if ($tokens < 0) {
            throw new InvalidArgumentException('Token count cannot be negative.');
        }

        $rateNanos = self::fromDecimalString($ratePerMillion);

        // tokens * rate / 1_000_000, exact integer division (rates are exact for Pixelforge).
        return intdiv($tokens * $rateNanos, 1_000_000);
    }

    /**
     * Apply markup percent and round UP to the next nanodollar so margin is never below the markup.
     */
    public static function withMarkup(int $providerCostNanos, int $markupPercent): int
    {
        if ($markupPercent < 0) {
            throw new InvalidArgumentException('Markup percent cannot be negative.');
        }

        $numerator = $providerCostNanos * (100 + $markupPercent);

        return intdiv($numerator + 99, 100);
    }
}
