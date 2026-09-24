<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    /**
     * Display the Privacy Policy page under Australian Privacy Principles.
     */
    public function privacy(SettingService $settingService): View
    {
        return view('public.privacy', [
            'venue' => $settingService->venue(),
        ]);
    }

    /**
     * Display the Terms & Conditions page.
     */
    public function terms(SettingService $settingService): View
    {
        return view('public.terms', [
            'venue' => $settingService->venue(),
        ]);
    }
}
