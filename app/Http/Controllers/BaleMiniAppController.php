<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BaleWebAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BaleMiniAppController extends Controller
{
    public function index()
    {
        return view('miniapp.bale', [
            'loginUrl' => route('auth.mobile'),
            'homeUrl' => route('home'),
            'authUrl' => route('miniapp.bale.authenticate'),
            'baleBotTokenConfigured' => (bool) config('services.bale.bot_token'),
        ]);
    }

    public function authenticate(Request $request, BaleWebAppService $bale)
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
            'phone' => ['required', 'string'],
        ]);

        $webApp = $bale->validateInitData($validated['init_data']);
        $mobile = $bale->normalizePhone($validated['phone']);

        if (!$mobile) {
            return response()->json([
                'message' => 'شماره موبایل ارسالی معتبر نیست.',
            ], 422);
        }

        $name = data_get($webApp, 'user.first_name');
        $user = User::firstOrNew(['mobile' => $mobile]);

        if (!$user->exists) {
            $user->name = $name ?: null;
            $user->mobile_verified_at = now();
            $user->save();
        } else {
            $dirty = false;

            if (!$user->name && $name) {
                $user->name = $name;
                $dirty = true;
            }

            if (!$user->mobile_verified_at) {
                $user->mobile_verified_at = now();
                $dirty = true;
            }

            if ($dirty) {
                $user->save();
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('home'),
        ]);
    }
}