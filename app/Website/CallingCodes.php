<?php

namespace App\Website;

/** Dial codes for the public demo form. The stored value is E.164. */
class CallingCodes
{
    /** @var array<int, array{iso: string, name: string, dial: string, min: int, max: int}> */
    private const COUNTRIES = [
        ['iso' => 'US', 'name' => 'United States', 'dial' => '1', 'min' => 10, 'max' => 10],
        ['iso' => 'CA', 'name' => 'Canada', 'dial' => '1', 'min' => 10, 'max' => 10],
        ['iso' => 'GB', 'name' => 'United Kingdom', 'dial' => '44', 'min' => 10, 'max' => 10],
        ['iso' => 'AU', 'name' => 'Australia', 'dial' => '61', 'min' => 9, 'max' => 9],
        ['iso' => 'NZ', 'name' => 'New Zealand', 'dial' => '64', 'min' => 8, 'max' => 10],
        ['iso' => 'IE', 'name' => 'Ireland', 'dial' => '353', 'min' => 7, 'max' => 9],
        ['iso' => 'MX', 'name' => 'Mexico', 'dial' => '52', 'min' => 10, 'max' => 10],
        ['iso' => 'BR', 'name' => 'Brazil', 'dial' => '55', 'min' => 10, 'max' => 11],
        ['iso' => 'AR', 'name' => 'Argentina', 'dial' => '54', 'min' => 10, 'max' => 10],
        ['iso' => 'CL', 'name' => 'Chile', 'dial' => '56', 'min' => 9, 'max' => 9],
        ['iso' => 'CO', 'name' => 'Colombia', 'dial' => '57', 'min' => 10, 'max' => 10],
        ['iso' => 'PE', 'name' => 'Peru', 'dial' => '51', 'min' => 9, 'max' => 9],
        ['iso' => 'DE', 'name' => 'Germany', 'dial' => '49', 'min' => 10, 'max' => 11],
        ['iso' => 'FR', 'name' => 'France', 'dial' => '33', 'min' => 9, 'max' => 9],
        ['iso' => 'ES', 'name' => 'Spain', 'dial' => '34', 'min' => 9, 'max' => 9],
        ['iso' => 'IT', 'name' => 'Italy', 'dial' => '39', 'min' => 9, 'max' => 10],
        ['iso' => 'NL', 'name' => 'Netherlands', 'dial' => '31', 'min' => 9, 'max' => 9],
        ['iso' => 'BE', 'name' => 'Belgium', 'dial' => '32', 'min' => 8, 'max' => 9],
        ['iso' => 'CH', 'name' => 'Switzerland', 'dial' => '41', 'min' => 9, 'max' => 9],
        ['iso' => 'AT', 'name' => 'Austria', 'dial' => '43', 'min' => 10, 'max' => 13],
        ['iso' => 'SE', 'name' => 'Sweden', 'dial' => '46', 'min' => 7, 'max' => 13],
        ['iso' => 'NO', 'name' => 'Norway', 'dial' => '47', 'min' => 8, 'max' => 8],
        ['iso' => 'DK', 'name' => 'Denmark', 'dial' => '45', 'min' => 8, 'max' => 8],
        ['iso' => 'FI', 'name' => 'Finland', 'dial' => '358', 'min' => 5, 'max' => 12],
        ['iso' => 'PL', 'name' => 'Poland', 'dial' => '48', 'min' => 9, 'max' => 9],
        ['iso' => 'PT', 'name' => 'Portugal', 'dial' => '351', 'min' => 9, 'max' => 9],
        ['iso' => 'GR', 'name' => 'Greece', 'dial' => '30', 'min' => 10, 'max' => 10],
        ['iso' => 'IN', 'name' => 'India', 'dial' => '91', 'min' => 10, 'max' => 10],
        ['iso' => 'CN', 'name' => 'China', 'dial' => '86', 'min' => 11, 'max' => 11],
        ['iso' => 'JP', 'name' => 'Japan', 'dial' => '81', 'min' => 10, 'max' => 10],
        ['iso' => 'KR', 'name' => 'South Korea', 'dial' => '82', 'min' => 9, 'max' => 10],
        ['iso' => 'SG', 'name' => 'Singapore', 'dial' => '65', 'min' => 8, 'max' => 8],
        ['iso' => 'HK', 'name' => 'Hong Kong', 'dial' => '852', 'min' => 8, 'max' => 8],
        ['iso' => 'PH', 'name' => 'Philippines', 'dial' => '63', 'min' => 10, 'max' => 10],
        ['iso' => 'MY', 'name' => 'Malaysia', 'dial' => '60', 'min' => 9, 'max' => 10],
        ['iso' => 'TH', 'name' => 'Thailand', 'dial' => '66', 'min' => 9, 'max' => 9],
        ['iso' => 'VN', 'name' => 'Vietnam', 'dial' => '84', 'min' => 9, 'max' => 10],
        ['iso' => 'ID', 'name' => 'Indonesia', 'dial' => '62', 'min' => 9, 'max' => 12],
        ['iso' => 'AE', 'name' => 'United Arab Emirates', 'dial' => '971', 'min' => 9, 'max' => 9],
        ['iso' => 'SA', 'name' => 'Saudi Arabia', 'dial' => '966', 'min' => 9, 'max' => 9],
        ['iso' => 'IL', 'name' => 'Israel', 'dial' => '972', 'min' => 8, 'max' => 9],
        ['iso' => 'ZA', 'name' => 'South Africa', 'dial' => '27', 'min' => 9, 'max' => 9],
        ['iso' => 'NG', 'name' => 'Nigeria', 'dial' => '234', 'min' => 10, 'max' => 10],
        ['iso' => 'EG', 'name' => 'Egypt', 'dial' => '20', 'min' => 10, 'max' => 10],
        ['iso' => 'KE', 'name' => 'Kenya', 'dial' => '254', 'min' => 9, 'max' => 9],
        ['iso' => 'TR', 'name' => 'Turkey', 'dial' => '90', 'min' => 10, 'max' => 10],
    ];

    /** @return array<int, array{iso: string, name: string, dial: string, flag: string, min: int, max: int}> */
    public static function all(): array
    {
        return array_map(fn (array $c) => $c + ['flag' => self::flag($c['iso'])], self::COUNTRIES);
    }

    public static function find(?string $iso): ?array
    {
        $iso = strtoupper((string) $iso);
        foreach (self::all() as $country) {
            if ($country['iso'] === $iso) {
                return $country;
            }
        }

        return null;
    }

    /** National number plus a known country, returned as E.164, or null when the shape is wrong. */
    public static function e164(?string $iso, ?string $national): ?string
    {
        $country = self::find($iso);
        if (! $country) {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string) $national) ?? '';
        if (str_starts_with($digits, $country['dial']) && strlen($digits) > $country['max']) {
            $digits = substr($digits, strlen($country['dial']));
        }
        $length = strlen($digits);
        if ($length < $country['min'] || $length > $country['max']) {
            return null;
        }
        if ($country['dial'] === '1' && ($digits[0] === '0' || $digits[0] === '1')) {
            return null;
        }
        $e164 = '+'.$country['dial'].$digits;

        return strlen($e164) <= 16 ? $e164 : null;
    }

    public static function flag(string $iso): string
    {
        $iso = strtoupper($iso);
        if (strlen($iso) !== 2) {
            return '';
        }

        return mb_chr(127397 + ord($iso[0])).mb_chr(127397 + ord($iso[1]));
    }
}
