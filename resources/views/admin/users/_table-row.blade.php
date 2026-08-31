<tr wire:key="admin-user-row-{{ $user->id }}" @if($collapsible ?? false) x-show="sectionExpanded" x-cloak @endif>
    <td>{{ $user->id }}</td>
    <td>
        <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="text-decoration-none fw-semibold text-dark">
            {{ $user->name ?? '—' }}
        </a>
    </td>
    <td><code>{{ $user->mobile }}</code></td>
    <td>
        @foreach($user->roles as $r)
            @php
                $roleFilterUrl = match ($r->name) {
                    'host' => route('admin.users.index', [
                        'section' => 'personnel',
                        'role' => filled($user->host_position_title) && !\App\Support\HostPositionTitles::isDefaultPositionLabel($user->host_position_title)
                            ? 'host_position:' . $user->host_position_title
                            : 'host',
                    ]),
                    'super_admin' => route('admin.users.index'),
                    default => route('admin.users.index', ['section' => 'personnel', 'role' => $r->name]),
                };
            @endphp
            <a wire:navigate href="{{ $roleFilterUrl }}" class="badge text-decoration-none {{ $r->name === 'super_admin' ? 'bg-danger' : ($r->name === 'host' ? 'bg-success' : 'bg-secondary') }}">{{ $user->roleBadgeLabel($r->name) }}</a>
        @endforeach
        @if($user->roles->isEmpty())
        <a wire:navigate href="{{ route('admin.users.index', ['section' => 'users']) }}" class="badge bg-light text-dark border text-decoration-none">{{ $user->roleBadgeLabel('guest') }}</a>
        @endif
    </td>
    <td class="small">{{ $user->veteranLabel() }}</td>
    <td>{{ $user->discount_percentage > 0 ? $user->discount_percentage.'%' : '—' }}</td>
    <td class="small text-muted">@jalali($user->created_at)</td>
    <td>
        @if($user->mobile_verified_at)
            <span class="badge bg-success">فعال</span>
        @else
            <span class="badge bg-danger">غیرفعال</span>
        @endif
    </td>
    <td>
        <div class="d-flex gap-1 flex-wrap">
            <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="مشاهده پروفایل">
                <i class="bi bi-eye"></i>
            </a>
            <a wire:navigate href="{{ route('admin.users.edit', $user) }}" class="btn btn-xs btn-outline-warning" style="padding:.2rem .5rem;font-size:.75rem;" title="ویرایش اطلاعات">
                <i class="bi bi-pencil"></i>
            </a>
            @if($user->hasRole('host'))
            <a wire:navigate href="{{ route('admin.accommodations.index', ['search'=> $user->name]) }}" class="btn btn-xs btn-outline-info" style="padding:.2rem .5rem;font-size:.75rem;" title="اقامتگاه‌های میزبان">
                <i class="bi bi-building"></i>
            </a>
            @endif
            <a wire:navigate href="{{ route('admin.bookings.index', ['search'=> $user->mobile]) }}" class="btn btn-xs btn-outline-secondary" style="padding:.2rem .5rem;font-size:.75rem;" title="رزروهای کاربر">
                <i class="bi bi-calendar-check"></i>
            </a>
            <button wire:click="toggleStatus({{ $user->id }})" class="btn btn-xs {{ $user->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" style="padding:.2rem .5rem;font-size:.75rem;" title="{{ $user->is_active ? 'غیرفعال کردن' : 'فعال کردن' }}">
                <i class="bi bi-{{ $user->is_active ? 'pause-fill' : 'play-fill' }}"></i>
            </button>
            @if(!$user->hasRole('super_admin'))
            <button wire:click="destroy({{ $user->id }})" data-swal-confirm="کاربر حذف شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="حذف"><i class="bi bi-trash"></i></button>
            @endif
        </div>
    </td>
</tr>
