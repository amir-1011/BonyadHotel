<div>

<div class="card shadow-sm mb-2">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div class="flex-grow-1 min-w-0">
                <ul class="nav nav-tabs mb-0 flex-nowrap overflow-auto">
                    <li class="nav-item">
                        <button type="button" wire:click="setSection('all')" class="nav-link py-2 {{ $section === 'all' ? 'active' : '' }}">
                            <i class="bi bi-grid me-1"></i>همه
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" wire:click="setSection('users')" class="nav-link py-2 {{ $section === 'users' ? 'active' : '' }}">
                            <i class="bi bi-people me-1"></i>مهمان‌ها
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" wire:click="setSection('roles')" class="nav-link py-2 {{ $section === 'roles' ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-1"></i>نقش‌ها
                        </button>
                    </li>
                </ul>

                @if($section === 'roles' && count($roleTabOptions) > 0)
                <ul class="nav nav-tabs mt-2 mb-0 flex-wrap">
                    @foreach($roleTabOptions as $option)
                    <li class="nav-item">
                        <button type="button"
                                wire:click="setRoleTab({{ \Illuminate\Support\Js::from($option['value']) }})"
                                class="nav-link py-1 small {{ $role === $option['value'] ? 'active' : '' }}">
                            {{ $option['label'] }}
                        </button>
                    </li>
                    @endforeach
                </ul>
                @elseif($section === 'roles')
                <div class="alert alert-info small py-1 px-2 mb-0 mt-2">
                    <i class="bi bi-info-circle me-1"></i>نقشی برای نمایش یافت نشد.
                </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <a href="{{ route('admin.users.export', $exportQuery) }}" class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
                    @if($hasActiveFilters)
                    <span class="badge bg-white text-success ms-1">فیلترشده</span>
                    @endif
                </a>
                <a wire:navigate href="{{ route('admin.users.create-host') }}" class="btn btn-sm btn-success">
                    <i class="bi bi-person-plus me-1"></i>افزودن میزبان
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2 mt-2 pt-2 border-top">
            <div class="flex-grow-1" style="min-width:min(100%, 220px);">
                <input type="text"
                       wire:model="searchInput"
                       wire:keydown.enter="applySearch"
                       class="form-control form-control-sm"
                       placeholder="جستجو نام / موبایل / کد ملی / کد حسابداری">
            </div>
            <button type="button"
                    wire:click="applySearch"
                    class="btn btn-sm btn-primary">
                اعمال
            </button>
            <button type="button"
                    wire:click="clearSearch"
                    class="btn btn-sm btn-outline-secondary"
                    title="پاک کردن جستجو"
                    @disabled($search === '' && $searchInput === '')>
                <i class="bi bi-x-lg"></i>
            </button>
            <x-tutorial-videos
                variant="inline"
                :videos="[['label' => 'ساختن میزبان', 'file' => 'ساختن میزبان.mp4']]"
            />
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-light">
                <tr>
                    <th class="col-index">#</th>
                    <th>نام</th>
                    <th>موبایل</th>
                    <th>نقش</th>
                    <th>گروه ایثارگری</th>
                    <th>تخفیف</th>
                    <th>تاریخ ثبت</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>
                        <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="text-decoration-none fw-semibold text-dark">
                            {{ $user->name ?? '—' }}
                        </a>
                    </td>
                    <td><code>{{ $user->mobile }}</code></td>
                    <td>
                        @foreach($user->roles as $r)
                            <a wire:navigate href="{{ route('admin.users.index', ['section' => 'roles', 'role' => $r->name]) }}" class="badge text-decoration-none {{ $r->name === 'super_admin' ? 'bg-danger' : ($r->name === 'host' ? 'bg-success' : 'bg-secondary') }}">{{ $user->roleBadgeLabel($r->name) }}</a>
                        @endforeach
                        @if($user->roles->isEmpty()) <a wire:navigate href="{{ route('admin.users.index', ['section' => 'users']) }}" class="badge bg-light text-dark border text-decoration-none">{{ $user->roleBadgeLabel('guest') }}</a> @endif
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
                @empty
                <tr><td colspan="9" class="text-center text-muted py-3">کاربری یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">{{ $users->links() }}</div>
</div>

</div>
