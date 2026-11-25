<?php

namespace App\Website;

/** Addition question kept in the session. The answer is never written into the form. */
class DemoCaptcha
{
    public const KEY = 'demo_captcha';

    public const CONTACT_KEY = 'contact_captcha';

    /** @return array{a: int, b: int} */
    public static function current(string $key = self::KEY): array
    {
        $stored = session($key);
        if (is_array($stored) && isset($stored['a'], $stored['b'])) {
            return ['a' => (int) $stored['a'], 'b' => (int) $stored['b']];
        }

        return self::refresh($key);
    }

    /** @return array{a: int, b: int} */
    public static function refresh(string $key = self::KEY): array
    {
        $question = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        session([$key => $question]);

        return $question;
    }

    public static function matches(mixed $answer, string $key = self::KEY): bool
    {
        $stored = session($key);
        if (! is_array($stored) || ! isset($stored['a'], $stored['b'])) {
            return false;
        }
        if (! is_scalar($answer) || ! preg_match('/^\d{1,2}$/', trim((string) $answer))) {
            return false;
        }

        return (int) $answer === ((int) $stored['a'] + (int) $stored['b']);
    }

    public static function forget(string $key = self::KEY): void
    {
        session()->forget($key);
    }
}
