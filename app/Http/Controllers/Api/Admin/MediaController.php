<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Streams a private document. Authorization is the signature itself: only
     * an admin could have obtained a valid signed link, and it dies within the
     * half hour. The signature also pins the exact path, so it cannot be edited
     * to read another file — the traversal guard below is only a second belt.
     */
    public function show(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path');

        abort_if(
            str_contains($path, '..') || ! preg_match('#^(applications|technicians)/#', $path),
            404
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
