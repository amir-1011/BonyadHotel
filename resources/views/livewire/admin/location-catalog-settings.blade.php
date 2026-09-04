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
            <button type="button" wire:click="$set('tab', 'counties')"
                    class="nav-link {{ $tab === 'counties' ? 'active' : '' }}">شهرستان‌ها</button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'types')"
                    class="nav-link {{ $tab === 'types' ? 'active' : '' }}">انواع اقامتگاه</button>
        </li>
    </ul>

    @if($tab === 'provinces')
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    کد سه‌رقمی هر استان پایه کدینگ حسابداری است: <strong>ذینفع (۱)</strong>، <strong>ارگان (۴)</strong>، <strong>پرسنل (۷)</strong>.
                    مثال مازندران (۵۱۵): ذینفع اول <code>515101</code>، ارگان اول <code>515401</code>، پرسنل اول <code>515701</code>.
                </div>
                <form wire:submit.prevent="saveProvinceAccountingCodes">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>نام استان</th>
                                    <th style="width:140px">کد حسابداری</th>
                                    <th class="text-center">تعداد شهر</th>
                                    <th class="text-end" style="width:100px">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($provinces as $province)
                                    <tr wire:key="province-row-{{ $province->id }}">
                                        <td>{{ $province->name }}</td>
                                        <td>
                                            <input type="text"
                                                   wire:model="provinceAccountingCodes.{{ $province->id }}"
                                                   class="form-control form-control-sm @error('provinceAccountingCodes.'.$province->id) is-invalid @enderror"
                                                   dir="ltr"
                                                   maxlength="3"
                                                   placeholder="۵۱۵">
                                            @error('provinceAccountingCodes.'.$province->id)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center">{{ $province->cities_count }}</td>
                                        <td class="text-end">
                                            <button type="button" wire:click="deleteProvince({{ $province->id }})"
                                                    wire:confirm="این استان حذف شود؟"
                                                    class="btn btn-sm btn-outline-danger"
                                                    @disabled($province->cities_count > 0)>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">استانی ثبت نشده است.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($provinces->isNotEmpty())
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <i class="bi bi-save me-1"></i>ذخیره کدهای حسابداری
                            </button>
                        </div>
                    @endif
                </form>
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

    @if($tab === 'counties')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>شهرستان</th>
                                <th>استان</th>
                                <th class="text-center">اقامتگاه</th>
                                <th class="text-end" style="width:100px">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($counties as $county)
                                <tr>
                                    <td>{{ $county->name }}</td>
                                    <td>{{ $county->province?->name }}</td>
                                    <td class="text-center">{{ $county->accommodations_count }}</td>
                                    <td class="text-end">
                                        <button wire:click="deleteCounty({{ $county->id }})"
                                                wire:confirm="این شهرستان حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($county->accommodations_count > 0)>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">شهرستانی ثبت نشده است.</td></tr>
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
        کاربران و مدیران می‌توانند هنگام ثبت اقامتگاه، استان، شهر، شهرستان یا نوع جدید اضافه کنند. فقط مدیر می‌تواند آیتم‌های بدون استفاده را از اینجا حذف کند.
    </div>
</div>
