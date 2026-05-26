<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>کاربران ({{ $users->total() }})</h5>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="جستجو نام / موبایل / کد ملی" value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">همه نقش‌ها</option>
                    @foreach($roles as $r)
                    <option value="{{ $r->name }}" {{ request('role') == $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button class="btn btn-sm btn-primary w-100">فیلتر</button>
            </div>
            <div class="col-6 col-md-2">
                <a wire:navigate href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک کردن</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>نام</th>
                    <th>موبایل</th>
                    <th>نقش</th>
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
                            <a wire:navigate href="{{ route('admin.users.index', ['role'=>$r->name]) }}" class="badge text-decoration-none {{ $r->name === 'super_admin' ? 'bg-danger' : ($r->name === 'host' ? 'bg-success' : 'bg-secondary') }}">{{ $r->name }}</a>
                        @endforeach
                        @if($user->roles->isEmpty()) <span class="badge bg-light text-dark border">guest</span> @endif
                    </td>
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
                <tr><td colspan="8" class="text-center text-muted py-4">کاربری یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $users->links() }}</div>
</div>

</div>