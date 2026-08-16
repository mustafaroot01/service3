<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Media
{
    /**
     * A public asset — category art, sliders, blog covers — served straight
     * off the web disk.
     */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * A private document — an applicant's ID or residence card — that lives off
     * the web-served disk. The link is signed and expires, so it works inside an
     * <img> tag (which cannot carry a bearer token) yet cannot be shared, cached,
     * or reused once the window closes. Only an admin ever receives one, because
     * only the permission-guarded media endpoints hand it out.
     */
    public static function secureUrl(?string $path): ?string
    {
        return $path
            ? URL::temporarySignedRoute('admin.media', now()->addMinutes(30), ['path' => $path])
            : null;
    }
}
