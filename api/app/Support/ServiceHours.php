<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ServiceHours
{
    /**
     * @param  array<string, mixed>  $hours
     */
    public static function isOpen(array $hours, string $timezone, ?CarbonInterface $at = null): bool
    {
        $moment = Carbon::parse($at ?? now())->timezone($timezone);
        $row = $hours[strtolower($moment->format('l'))] ?? null;
        if (! is_array($row) || ($row['closed'] ?? false) === true) {
            return false;
        }

        $openText = $row['open'] ?? null;
        $closeText = $row['close'] ?? null;
        if (! is_string($openText) || ! is_string($closeText)) {
            return false;
        }

        $open = Carbon::parse($moment->toDateString().' '.$openText, $timezone);
        $close = Carbon::parse($moment->toDateString().' '.$closeText, $timezone);
        if ($close->lessThanOrEqualTo($open)) {
            return false;
        }

        return $moment->betweenIncluded($open, $close);
    }

    /**
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    public static function defaults(): array
    {
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = ['open' => '07:00', 'close' => '22:00', 'closed' => false];
        }

        return $hours;
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultNotifications(): array
    {
        return [
            'in_app' => true,
            'email' => false,
            'sms' => false,
            'whatsapp' => false,
        ];
    }
}
