<?php

use Illuminate\Support\Facades\Route;

/**
 * The panel is a single page app, so every address returns it and Vue Router
 * decides what to show. Asset folders are excluded: a missing build file must
 * answer 404, not this HTML — the browser would try to run the page as
 * JavaScript and report a syntax error instead of the missing file.
 */
Route::get('{any?}', fn () => view('application'))
    ->where('any', '^(?!build/|storage/|api/).*$')
    ->name('app');
