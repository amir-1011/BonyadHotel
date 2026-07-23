@props([
    'page',
    'action' => null,
    'any' => null,
    'panel' => 'host',
])

@php
    use App\Support\HostPermissions;

    $user = auth()->user();
    $panelKey = $panel ?? 'host';

    $allowed = $panelKey !== 'host'
        || ($user && (
            $user->isAdmin()
            || ($any !== null
                ? $user->hostCanAny($page, is_array($any) ? $any : [$any])
                : $user->hostCan($page, $action ?? HostPermissions::ACTION_READ))
        ));
@endphp

@if($allowed)
    {{ $slot }}
@endif
