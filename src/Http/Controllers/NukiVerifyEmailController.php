<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http\Controllers;

use Darvis\Nuki\Models\NukiUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NukiVerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        if (config('nuki.auth_users.email_verification.enabled', true) === false) {
            abort(404);
        }

        $user = NukiUser::query()->find($id);

        if ($user === null) {
            abort(404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        $request->session()->forget('nuki.pending_verification_user_id');

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('nuki.auth.login')
                ->with('status', (string) __('nuki::nuki.auth.info.email_verified'));
        }

        $user->markEmailAsVerified();

        return redirect()
            ->route('nuki.auth.login')
            ->with('status', (string) __('nuki::nuki.auth.info.email_verified'));
    }
}
