<div>
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form wire:submit.prevent class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">وضعیت</label>
                    <select wire:model="newStatus" class="form-select form-select-sm">
                        @foreach(\App\Models\Program::statusOptions() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" wire:click="updateStatus" class="btn btn-sm btn-primary w-100">به‌روزرسانی وضعیت</button>
                </div>
            </form>
        </div>
    </div>

    <x-program.show-details :program="$program" panel="admin">
        <x-slot name="guestManager">
            @include('components.program.guest-list-manager', ['program' => $program, 'panel' => 'admin'])
        </x-slot>
        <x-slot name="financialManager">
            @include('components.program.financial-manager', ['program' => $program, 'panel' => 'admin'])
        </x-slot>
    </x-program.show-details>
</div>
