<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\View\View;

/**
 * S01, FR32, FR80, FR91: Public homepage.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
    ) {}

    public function index(): View
    {
        return view('public.home', [
            'venue' => $this->settingService->venue(),
        ]);
    }
}
