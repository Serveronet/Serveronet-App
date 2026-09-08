<?php

namespace App\Http\Controllers;

use App\Dicts\LandingText;

class LandingController extends Controller
{
    public function showLanding()
    {
        $landingTexts = LandingText::$blocks;
        $roadMapBlocks = LandingText::$roadMapBlocks;

        return view('landing', compact('landingTexts', 'roadMapBlocks'));
    }
}
