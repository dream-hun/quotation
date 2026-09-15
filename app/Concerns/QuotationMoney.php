<?php

declare(strict_types=1);

namespace App\Concerns;

use BcMath\Number;
use RoundingMode;

final class QuotationMoney
{
    public const string VERSION = 'decimal-v1';

    public static function amount(string $value, int $precision = 4, string $field = 'money'): Number
    {
        ProcurementGuard::require(is_numeric($value), 'Enter a decimal amount.', $field);
        ProcurementGuard::require((bool) preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $value), 'Enter a nonnegative decimal amount with at most four decimal places.', $field);
        $number = new Number($value);
        ProcurementGuard::require($number->compare($number->round($precision, RoundingMode::HalfAwayFromZero)) === 0, 'Amount exceeds the currency precision.', $field);

        return $number;
    }

    /** @return array{line_net: numeric-string, line_total: numeric-string} */
    public static function line(string $quantity, string $price, string $discount, string $tax, int $precision): array
    {
        $extension = self::amount($quantity)->mul(self::amount($price));
        $discountAmount = self::amount($discount, $precision);
        ProcurementGuard::require($discountAmount->compare($extension) <= 0, 'Discount cannot exceed quantity multiplied by unit price.');
        $net = $extension->sub($discountAmount)->round($precision, RoundingMode::HalfAwayFromZero);
        $total = $net->add(self::amount($tax, $precision));

        return ['line_net' => self::bounded($net), 'line_total' => self::bounded($total)];
    }

    /** @return numeric-string */
    public static function bounded(Number $number): string
    {
        ProcurementGuard::require($number->compare('9999999999999999.9999') <= 0, 'Calculated amount is too large.');

        return (string) $number;
    }
}
