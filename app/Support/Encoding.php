<?php

namespace App\Support;

class Encoding
{
    /**
     * Remove a leading UTF-8 byte-order mark (BOM) if present.
     *
     * A BOM on a CSV file attaches to the first header cell and breaks
     * header resolution (trim() does not strip it).
     */
    public static function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    /**
     * Coerce a string to valid UTF-8.
     *
     * Already-valid UTF-8 is returned unchanged (so legitimate multibyte
     * text is never double-encoded). Otherwise the bytes are decoded as
     * Windows-1252 — the encoding the real supervisor CSV uses — so that
     * e.g. the 0x96 byte becomes a proper en dash instead of mojibake.
     */
    public static function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
