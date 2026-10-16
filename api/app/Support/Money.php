<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function applyBps(int $amountMinor, int $bps): int
    {
        if ($amountMinor < 0 || $bps < 0 || $bps > 10000) {
            throw new InvalidArgumentException('Amount and rate must be a non-negative rate up to 100%.');
        }

        return intdiv($amountMinor * $bps + 5000, 10000);
    }

    /**
     * @return array{subtotal_minor: int, service_charge_minor: int, tax_minor: int, total_minor: int}
     */
    public static function quote(int $subtotalMinor, int $serviceChargeBps, int $taxBps): array
    {
        $charge = self::applyBps($subtotalMinor, $serviceChargeBps);
        $tax = self::applyBps($subtotalMinor + $charge, $taxBps);

        return [
            'subtotal_minor' => $subtotalMinor,
            'service_charge_minor' => $charge,
            'tax_minor' => $tax,
            'total_minor' => $subtotalMinor + $charge + $tax,
        ];
    }

    public static function format(int $minor, string $currency = 'NGN'): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);
        $major = intdiv($minor, 100);
        $cents = str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) $major) ?? (string) $major;
        $symbol = $currency === 'NGN' ? '₦' : $currency.' ';

        return $symbol.$sign.$grouped.'.'.$cents;
    }

    public static function bpsLabel(int $bps): string
    {
        $whole = intdiv($bps, 100);
        $fraction = $bps % 100;
        if ($fraction === 0) {
            return $whole.'%';
        }

        $text = rtrim(rtrim(sprintf('%d.%02d', $whole, $fraction), '0'), '.');

        return $text.'%';
    }
}
