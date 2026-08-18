<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * the public, unauthenticated Landing Page (`GET /`,
 * `landing.show`). Thin by design: no business logic, the marketing
 * content itself lives entirely in `resources/views/landing/show.blade.php`.
 */
class LandingPageController extends Controller
{
    public function show(): View
    {
        return view('landing.show');
    }
}
