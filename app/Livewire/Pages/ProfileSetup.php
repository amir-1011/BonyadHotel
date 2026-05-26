<?php

namespace App\Livewire\Pages;

use App\Services\NationalIdVerificationService;
use Illuminate\Support\Facades\Auth;
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
        $user             = Auth::user();
        $nationalIdRule   = $user->national_id ? 'nullable' : 'required';

        $this->validate([
            'name'       => ['required', 'string', 'min:2', 'max:100'],
            'nationalId' => [$nationalIdRule, 'digits:10'],
        ], [
            'name.required'       => 'نام الزامی است.',
            'name.min'            => 'نام باید حداقل ۲ کاراکتر باشد.',
            'nationalId.required' => 'کد ملی الزامی است.',
            'nationalId.digits'   => 'کد ملی باید ۱۰ رقم باشد.',
        ]);

        $updateData = ['name' => $this->name];

        if ($this->nationalId) {
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

    public function render()
    {
        return view('profile.setup');
    }
}
