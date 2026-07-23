<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\NationalIdVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $nationalId = preg_replace('/\D/', '', (string) $request->input('national_id', ''));
        $request->merge(['national_id' => $nationalId ?: null]);

        $requiresNationalId = !$user->national_id && !$user->name;

        $validated = $request->validate([
            'name' => [
                $user->name ? 'sometimes' : 'required',
                'string',
                'min:2',
                'max:100',
            ],
            'national_id' => array_filter([
                $requiresNationalId ? 'required' : 'nullable',
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_id')->ignore($user->id),
            ]),
        ], [
            'name.required'        => 'نام الزامی است.',
            'name.min'             => 'نام باید حداقل ۲ کاراکتر باشد.',
            'national_id.required' => 'کد ملی الزامی است.',
            'national_id.digits'   => 'کد ملی باید ۱۰ رقم باشد.',
            'national_id.unique'   => $this->nationalIdDuplicateMessage($nationalId, $user->id),
        ]);

        $updateData = [];

        if (array_key_exists('name', $validated)) {
            $updateData['name'] = $validated['name'];
        } elseif (!$user->name) {
            return response()->json(['message' => 'نام الزامی است.'], 422);
        }

        if ($request->filled('national_id') && $nationalId !== $user->national_id) {
            $result = app(NationalIdVerificationService::class)->verify($nationalId);
            if (!$result['valid']) {
                return response()->json(['message' => $result['message']], 422);
            }

            $updateData['national_id']             = $nationalId;
            $updateData['veteran_type']            = $result['veteran_type'];
            $updateData['secondary_veteran_type']  = null;
            $updateData['discount_percentage']     = $result['discount'];
            $updateData['national_id_verified_at'] = now();
        } elseif ($requiresNationalId) {
            return response()->json(['message' => 'کد ملی الزامی است.'], 422);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'پروفایل با موفقیت به‌روزرسانی شد.',
            'user'    => new UserResource($user->fresh()),
        ]);
    }

    public function verifyNationalId(Request $request): JsonResponse
    {
        $user = $request->user();
        $nationalId = preg_replace('/\D/', '', (string) $request->input('national_id', ''));
        $request->merge(['national_id' => $nationalId]);

        $request->validate([
            'national_id' => [
                'required',
                'digits:10',
                Rule::unique('users', 'national_id')->ignore($user->id),
            ],
        ], [
            'national_id.unique' => $this->nationalIdDuplicateMessage($nationalId, $user->id),
        ]);

        $result = app(NationalIdVerificationService::class)->verify($nationalId);
        if (!$result['valid']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $user->update([
            'national_id'             => $nationalId,
            'veteran_type'            => $result['veteran_type'],
            'secondary_veteran_type'  => null,
            'discount_percentage'     => $result['discount'],
            'national_id_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'کد ملی با موفقیت تأیید شد.',
            'user'    => new UserResource($user->fresh()),
        ]);
    }

    private function nationalIdDuplicateMessage(string $nationalId, int $userId): string
    {
        $existing = User::query()
            ->where('national_id', $nationalId)
            ->where('id', '!=', $userId)
            ->exists();

        if (!$existing) {
            return 'این کد ملی قبلاً برای حساب دیگری ثبت شده است.';
        }

        return 'این کد ملی قبلاً برای حساب دیگری ثبت شده است. '
            . 'اگر قبلاً با شماره دیگری ثبت‌نام کرده‌اید، لطفاً با همان شماره وارد شوید.';
    }
}
