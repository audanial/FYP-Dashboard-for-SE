<?php

namespace App\Support;

class SupervisorName
{
    /**
     * Honorific titles and relationship particles dropped during slugging, so
     * a messy CSV supervisor string collapses onto the roster's canonical name.
     * Matched as whole tokens (never as substrings of a real name).
     */
    private const DROP_TOKENS = [
        'dr', 'ts', 'assoc', 'prof', 'professor', 'bin', 'binti',
    ];

    /**
     * Normalize a supervisor name into a stable match key.
     *
     * Strips titles ("Dr.", "Ts.", "Assoc. Prof.", "Prof."), relationship
     * particles ("bin", "binti", "a/l", "a/p"), dashes, and surrounding/inner
     * whitespace; lowercases; and joins the remaining tokens with underscores.
     */
    public static function slug(string $name): string
    {
        $value = mb_strtolower(trim($name));

        // a/l and a/p carry a slash; remove them before generic punctuation
        // handling turns them into stray "a"/"l"/"p" tokens.
        $value = preg_replace('#\ba/[lp]\b#u', ' ', $value);

        // Collapse every run of non-alphanumeric characters (dots, dashes,
        // newlines, repeated spaces) into a single token boundary.
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        $tokens = array_filter(
            explode(' ', trim($value)),
            fn (string $token) => $token !== '' && ! in_array($token, self::DROP_TOKENS, true),
        );

        return implode('_', $tokens);
    }
}
