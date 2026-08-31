<div>
    <x-program.show-details :program="$program" panel="host">
        <x-slot name="guestManager">
            @include('components.program.guest-list-manager', ['program' => $program, 'panel' => 'host'])
        </x-slot>
        <x-slot name="financialManager">
            @include('components.program.financial-manager', ['program' => $program, 'panel' => 'host'])
        </x-slot>
    </x-program.show-details>
</div>
