<?php

namespace App\Website;

/** Addition question kept in the session. The answer is never written into the form. */
class DemoCaptcha
{
    public const KEY = 'demo_captcha';

    /** @return array{a: int, b: int} */
    public static function current(): array
    {
        $stored = session(self::KEY);
        if (is_array($stored) && isset($stored['a'], $stored['b'])) {
            return ['a' => (int) $stored['a'], 'b' => (int) $stored['b']];
        }

        return self::refresh();
    }

    /** @return array{a: int, b: int} */
    public static function refresh(): array
    {
        $question = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
        session([self::KEY => $question]);

        return $question;
    }

    public static function matches(mixed $answer): bool
    {
        $stored = session(self::KEY);
        if (! is_array($stored) || ! isset($stored['a'], $stored['b'])) {
            return false;
        }
        if (! is_scalar($answer) || ! preg_match('/^\d{1,2}$/', trim((string) $answer))) {
            return false;
        }

        return (int) $answer === ((int) $stored['a'] + (int) $stored['b']);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
