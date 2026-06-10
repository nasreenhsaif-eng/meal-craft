<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * Public marketing welcome screen — guests only.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect($request->user()->homePath());
        }

        return view('welcome', [
            'welcomeConfig' => [
                'loginHref' => route('login'),
            ],
        ]);
    }
}
