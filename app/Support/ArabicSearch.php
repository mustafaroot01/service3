<?php

namespace App\Support;

/**
 * Folds Arabic text so a query matches regardless of how the typist wrote it:
 * MySQL's utf8mb4_unicode_ci does NOT treat أ/إ/آ as ا, ة as ه, or ى as ي, and
 * it keeps the diacritics — so «صيانه» would miss «صيانة» without this. The same
 * fold is applied to the query (in PHP) and to the column (in SQL) so both sides
 * meet on identical text.
 */
class ArabicSearch
{
    /**
     * Maps every character we fold away. Diacritics and the tatweel collapse to
     * nothing; the hamza family and its cousins collapse to their bare letter.
     */
    private const MAP = [
        // Harakat (tashkeel) — removed
        "\u{064B}" => '', // ً
        "\u{064C}" => '', // ٌ
        "\u{064D}" => '', // ٍ
        "\u{064E}" => '', // َ
        "\u{064F}" => '', // ُ
        "\u{0650}" => '', // ِ
        "\u{0651}" => '', // ّ
        "\u{0652}" => '', // ْ
        "\u{0670}" => '', // ٰ  (dagger alef)
        "\u{0640}" => '', // ـ  (tatweel)

        // Letter unification
        "\u{0622}" => "\u{0627}", // آ → ا
        "\u{0623}" => "\u{0627}", // أ → ا
        "\u{0625}" => "\u{0627}", // إ → ا
        "\u{0671}" => "\u{0627}", // ٱ → ا
        "\u{0624}" => "\u{0648}", // ؤ → و
        "\u{0626}" => "\u{064A}", // ئ → ي
        "\u{0629}" => "\u{0647}", // ة → ه
        "\u{0649}" => "\u{064A}", // ى → ي
    ];

    public static function normalize(?string $value): string
    {
        $value = strtr((string) $value, self::MAP);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim(mb_strtolower($value));
    }

    /**
     * The SQL mirror of normalize(): wraps a column in the same fold so it can be
     * compared against an already-normalized, bound query value. $column is fixed
     * caller code (never user input), so interpolating it is safe.
     */
    public static function sql(string $column): string
    {
        $expression = $column;

        foreach (self::MAP as $from => $to) {
            $expression = sprintf("REPLACE(%s, '%s', '%s')", $expression, $from, $to);
        }

        return "LOWER({$expression})";
    }
}
