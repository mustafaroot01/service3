<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    public const MAX = 100;

    /**
     * A caller-supplied page size is untrusted input. A negative value makes
     * MySQL drop the LIMIT and stream the whole table, and a huge positive one
     * does the same by another road, so both are clamped to a sane band. This
     * is the single gate every list endpoint — admin and customer — passes
     * through, so the contract's "max 100" holds everywhere.
     */
    public static function perPage(Request $request, int $default = 15): int
    {
        return max(1, min(self::MAX, (int) $request->input('per_page', $default)));
    }
}
