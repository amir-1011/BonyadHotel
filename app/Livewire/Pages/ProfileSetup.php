<?php

namespace App\Livewire\Pages;

use App\Models\User;
use App\Services\NationalIdVerificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'تکمیل پروفایل'])]
class ProfileSetup extends Component
{
    public string $name       = '';
    public string $nationalId = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name       = $user->name ?? '';
        $this->nationalId = $user->national_id ?? '';
    }

    public function save(): void
    {
        $user = Auth::user();
        $this->nationalId = preg_replace('/\D/', '', $this->nationalId);
        $nationalIdRule = $user->national_id ? 'nullable' : 'required';

        $this->validate([
            'name'       => ['required', 'string', 'min:2', 'max:100'],
            'nationalId' => [
                $nationalIdRule,
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_id')->ignore($user->id),
            ],
        ], [
            'name.required'       => 'نام الزامی است.',
            'name.min'            => 'نام باید حداقل ۲ کاراکتر باشد.',
            'nationalId.required' => 'کد ملی الزامی است.',
            'nationalId.digits'   => 'کد ملی باید ۱۰ رقم باشد.',
            'nationalId.unique'   => $this->nationalIdDuplicateMessage($user->id),
        ]);

        $updateData = ['name' => $this->name];

        if ($this->nationalId && $this->nationalId !== $user->national_id) {
            $result = app(NationalIdVerificationService::class)->verify($this->nationalId);
            if (!$result['valid']) {
                $this->addError('nationalId', $result['message']);
                return;
            }
            $updateData['national_id']             = $this->nationalId;
            $updateData['veteran_type']            = $result['veteran_type'];
            $updateData['discount_percentage']     = $result['discount'];
            $updateData['national_id_verified_at'] = now();
        }

        $user->update($updateData);

        session()->flash('status', 'پروفایل با موفقیت تکمیل شد.');
        $this->redirectRoute('home', navigate: true);
    }

    private function nationalIdDuplicateMessage(int $userId): string
    {
        $existing = User::query()
            ->where('national_id', $this->nationalId)
            ->where('id', '!=', $userId)
            ->exists();

        if (!$existing) {
            return 'این کد ملی قبلاً برای حساب دیگری ثبت شده است.';
        }

        return 'این کد ملی قبلاً برای حساب دیگری ثبت شده است. '
            . 'اگر قبلاً با شماره دیگری ثبت‌نام کرده‌اید، لطفاً با همان شماره وارد شوید.';
    }

    public function render()
    {
        return view('profile.setup');
    }
}
