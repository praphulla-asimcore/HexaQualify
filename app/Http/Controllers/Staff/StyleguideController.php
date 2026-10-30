<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StyleguideController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Styleguide');
    }
}
