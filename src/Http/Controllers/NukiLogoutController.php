<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Controllers;

use Darvis\Nuki\Auth\Users\AuthConfigRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class NukiLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard(AuthConfigRegistrar::GUARD)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect((string) config('nuki.auth_users.redirect_after_logout', '/nuki/login'));
    }
}
