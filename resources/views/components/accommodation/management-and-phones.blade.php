<div class="col-md-6">
    <label class="form-label small fw-semibold">وضعیت اداره</label>
    <select wire:model="managementStatus" class="form-select @error('managementStatus') is-invalid @enderror" required>
        <option value="">انتخاب کنید</option>
        @foreach(\App\Models\Accommodation::managementStatusOptions() as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('managementStatus')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-12">
    <label class="form-label small fw-semibold">
        <i class="bi bi-telephone me-1"></i>شماره‌های تماس اقامتگاه
        <span class="text-muted fw-normal">(ثابت یا همراه)</span>
    </label>

    <div class="d-flex flex-column gap-2">
        @foreach($phoneNumbers as $index => $phone)
        <div class="row g-2 align-items-start" wire:key="phone-{{ $index }}">
            <div class="col-md-2 col-6">
                <select wire:model="phoneNumbers.{{ $index }}.type" class="form-select form-select-sm">
                    <option value="mobile">همراه</option>
                    <option value="landline">ثابت</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <input
                    type="text"
                    wire:model="phoneNumbers.{{ $index }}.number"
                    class="form-control form-control-sm @error('phoneNumbers.'.$index.'.number') is-invalid @enderror"
                    placeholder="{{ ($phone['type'] ?? 'mobile') === 'mobile' ? '09123456789' : '02112345678' }}"
                    dir="ltr"
                >
                @error('phoneNumbers.'.$index.'.number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5 col-10">
                <input
                    type="text"
                    wire:model="phoneNumbers.{{ $index }}.note"
                    class="form-control form-control-sm @error('phoneNumbers.'.$index.'.note') is-invalid @enderror"
                    placeholder="توضیحات — مثلاً: پذیرش، مدیر، رزرو"
                >
                @error('phoneNumbers.'.$index.'.note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2 col-2">
                @if(count($phoneNumbers) > 1)
                <button type="button" wire:click="removePhoneNumber({{ $index }})" class="btn btn-sm btn-outline-danger w-100" title="حذف">
                    <i class="bi bi-trash"></i>
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <button type="button" wire:click="addPhoneNumber" class="btn btn-sm btn-outline-primary mt-2">
        <i class="bi bi-plus-lg me-1"></i>افزودن شماره
    </button>
</div>
