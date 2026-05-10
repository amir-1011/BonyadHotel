<?php

namespace App\Http\Controllers;

use App\Services\NationalIdVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function setup()
    {
        return view('profile.setup');
    }

    public function saveSetup(Request $request)
    {
        $user = Auth::user();
        
        // Make national_id required only if user doesn't have one yet
        $nationalIdRule = $user->national_id ? 'nullable' : 'required';
        
        $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:100'],
            'national_id' => [$nationalIdRule, 'digits:10'],
        ], [
            'name.required'        => 'نام الزامی است.',
            'name.min'             => 'نام باید حداقل ۲ کاراکتر باشد.',
            'national_id.required' => 'کد ملی الزامی است.',
            'national_id.digits'   => 'کد ملی باید ۱۰ رقم باشد.',
        ]);

        $updateData = [
            'name' => $request->input('name'),
        ];

        // If national_id is provided, validate and update it
        if ($request->filled('national_id')) {
            $result = app(NationalIdVerificationService::class)
                ->verify($request->input('national_id'));

            if (!$result['valid']) {
                return back()->withErrors(['national_id' => $result['message']]);
            }

            $updateData['national_id'] = $request->input('national_id');
            $updateData['veteran_type'] = $result['veteran_type'];
            $updateData['discount_percentage'] = $result['discount'];
            $updateData['national_id_verified_at'] = now();
        }

        $user->update($updateData);

        return redirect()->route('home')
            ->with('status', 'پروفایل با موفقیت تکمیل شد.');
    }

    public function index()
    {
        $user     = Auth::user();
        $bookings = $user->bookings()->with('accommodation.city.province')
            ->latest()->paginate(10);

        return view('profile.index', compact('user', 'bookings'));
    }

    public function verifyNationalId(Request $request)
    {
        $request->validate([
            'national_id' => ['required', 'digits:10'],
        ]);

        $user   = Auth::user();
        $result = app(NationalIdVerificationService::class)
            ->verify($request->input('national_id'));

        if (!$result['valid']) {
            return back()->withErrors(['national_id' => $result['message']]);
        }

        $user->update([
            'national_id'             => $request->input('national_id'),
            'veteran_type'            => $result['veteran_type'],
            'discount_percentage'     => $result['discount'],
            'national_id_verified_at' => now(),
        ]);

        return back()->with('status', 'کد ملی با موفقیت تأیید شد.');
    }
}
