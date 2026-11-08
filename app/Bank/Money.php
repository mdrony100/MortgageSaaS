<?php

namespace App\Bank;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class Money
{
    public static function minor(?string $s): ?int
    {
        if ($s === null) {
            return null;
        }
        if (! preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/D', $s)) {
            throw new InvalidArgumentException('Invalid decimal money value');
        }

        return BigDecimal::of($s)->multipliedBy(100)->toScale(0, RoundingMode::Unnecessary)->toInt();
    }

    public static function dollars(int $minor): string
    {
        return (string) BigDecimal::of($minor)->dividedBy(100, 2, RoundingMode::Unnecessary);
    }

    public static function monthly(int $eligible, string $ownership, string $remaining, int $months): array
    {
        $product = BigDecimal::of($eligible)->multipliedBy($ownership)->multipliedBy($remaining);
        $net = $product->dividedBy(10000, 12, RoundingMode::Unnecessary);
        $exact = $net->dividedBy(100 * $months, 12, RoundingMode::HalfUp);

        return ['adjusted_minor_exact' => (string) $net, 'monthly_exact' => (string) $exact, 'monthly_rational_numerator' => (string) $product, 'monthly_rational_denominator' => (string) (1000000 * $months), 'monthly_decimal_note' => '12-place representation; rational fields are exact', 'monthly_display' => (string) $product->dividedBy(1000000 * $months, 2, RoundingMode::HalfUp), 'rounding' => 'HALF_UP at display (2 decimal USD)'];
    }
}
