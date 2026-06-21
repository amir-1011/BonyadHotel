<div>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'provinces')"
                    class="nav-link {{ $tab === 'provinces' ? 'active' : '' }}">استان‌ها</button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'cities')"
                    class="nav-link {{ $tab === 'cities' ? 'active' : '' }}">شهرها</button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'types')"
                    class="nav-link {{ $tab === 'types' ? 'active' : '' }}">انواع اقامتگاه</button>
        </li>
    </ul>

    @if($tab === 'provinces')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>نام استان</th>
                                <th class="text-center">تعداد شهر</th>
                                <th class="text-end" style="width:100px">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($provinces as $province)
                                <tr>
                                    <td>{{ $province->name }}</td>
                                    <td class="text-center">{{ $province->cities_count }}</td>
                                    <td class="text-end">
                                        <button wire:click="deleteProvince({{ $province->id }})"
                                                wire:confirm="این استان حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($province->cities_count > 0)>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">استانی ثبت نشده است.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'cities')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>شهر</th>
                                <th>استان</th>
                                <th class="text-center">اقامتگاه</th>
                                <th class="text-end" style="width:100px">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cities as $city)
                                <tr>
                                    <td>{{ $city->name }}</td>
                                    <td>{{ $city->province?->name }}</td>
                                    <td class="text-center">{{ $city->accommodations_count }}</td>
                                    <td class="text-end">
                                        <button wire:click="deleteCity({{ $city->id }})"
                                                wire:confirm="این شهر حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($city->accommodations_count > 0)>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">شهری ثبت نشده است.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'types')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>نام نوع</th>
                                <th>کلید</th>
                                <th class="text-center">پیش‌فرض</th>
                                <th class="text-end" style="width:100px">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accommodationTypes as $type)
                                <tr>
                                    <td>{{ $type->label }}</td>
                                    <td><code class="small">{{ $type->key }}</code></td>
                                    <td class="text-center">
                                        @if($type->is_system)
                                            <span class="badge bg-secondary">سیستمی</span>
                                        @else
                                            <span class="badge bg-info text-dark">سفارشی</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button wire:click="deleteType({{ $type->id }})"
                                                wire:confirm="این نوع حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($type->isInUse())>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">نوعی ثبت نشده است.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="alert alert-info small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        میزبانان و مدیران می‌توانند هنگام ثبت اقامتگاه، استان، شهر یا نوع جدید اضافه کنند. فقط مدیر می‌تواند آیتم‌های بدون استفاده را از اینجا حذف کند.
    </div>
</div>
