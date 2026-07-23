<div>
    <x-veteran-policy.discount-matrix-styles />

    @php
        $canEditVeteranPolicy = ($panel ?? 'admin') !== 'host'
            || auth()->user()?->hostCan('accommodations.veteran-policy', 'edit');
    @endphp

    @if(($panel ?? 'admin') === 'host' && !$canEditVeteranPolicy)
    <div class="alert alert-warning small"><i class="bi bi-lock me-1"></i>فقط مجوز مشاهده دارید — دکمه‌های ذخیره و تغییر نمایش داده نمی‌شوند.</div>
    @endif

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a wire:navigate href="{{ $backRoute }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-right me-1"></i>بازگشت
        </a>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <p class="text-muted small mb-0">مدیریت درصد تخفیف اقامت، سقف استفاده و تخفیف هر خدمت بر اساس گروه ایثارگری — مختص این اقامتگاه</p>
        </div>
        @if($panel === 'admin')
        <button type="button"
                wire:click="restoreDefaultVeteranPolicy"
                data-swal-confirm="تنظیمات سراسری ایثارگری (صفحه مدیریت کلی) روی این اقامتگاه بازگردانی شود؟ گروه‌ها، خدمات و ماتریس تخفیف فعلی جایگزین می‌شوند."
                class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-counterclockwise me-1"></i>بازگردانی از تنظیمات سراسری
        </button>
        @endif
    </div>

    <x-tutorial-videos :videos="[
        ['label' => 'قوانین ایثارگری', 'file' => 'قوانین ایثارگری.mp4'],
    ]" />

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'groups')" class="nav-link {{ $tab === 'groups' ? 'active' : '' }}">
                <i class="bi bi-people-fill me-1"></i>گروه‌های ایثارگری
            </button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'services')" class="nav-link {{ $tab === 'services' ? 'active' : '' }}">
                <i class="bi bi-list-check me-1"></i>فهرست خدمات
            </button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'matrix')" class="nav-link {{ $tab === 'matrix' ? 'active' : '' }}">
                <i class="bi bi-table me-1"></i>تخفیف خدمات
            </button>
        </li>
    </ul>

    @if($tab === 'groups')
    <form wire:submit="saveGroups">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">گروه‌های جانبازی و سقف استفاده اقامت</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>گروه</th>
                                <th style="width:90px">تخفیف اقامت ٪</th>
                                <th style="width:90px" class="d-none">شب/تکفل</th>
                                <th style="width:90px">سقف دوره</th>
                                <th style="width:90px">دوره (ماه)</th>
                                <th>یادداشت</th>
                                <th style="width:60px">فعال</th>
                                @if($panel === 'admin')
                                <th style="width:50px"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $i => $group)
                            <tr wire:key="grp-{{ $group['id'] }}">
                                <td>
                                    <input type="text" wire:model="groups.{{ $i }}.label" class="form-control form-control-sm">
                                    <div class="text-muted" style="font-size:.7rem">{{ $group['key'] }}</div>
                                </td>
                                <td><input type="number" wire:model="groups.{{ $i }}.accommodation_discount" min="0" max="100" class="form-control form-control-sm"></td>
                                <td class="d-none"><input type="number" wire:model="groups.{{ $i }}.nights_per_dependent" min="1" class="form-control form-control-sm"></td>
                                <td><input type="number" wire:model="groups.{{ $i }}.max_nights_per_period" min="1" class="form-control form-control-sm"></td>
                                <td><input type="number" wire:model="groups.{{ $i }}.period_months" min="1" class="form-control form-control-sm"></td>
                                <td><input type="text" wire:model="groups.{{ $i }}.usage_notes" class="form-control form-control-sm" placeholder="توضیح سقف استفاده"></td>
                                <td class="text-center"><input type="checkbox" wire:model="groups.{{ $i }}.is_active" class="form-check-input"></td>
                                @if($panel === 'admin')
                                <td class="text-center">
                                    <button type="button"
                                            wire:click="removeVeteranGroup({{ $group['id'] }})"
                                            data-swal-confirm="گروه «{{ $group['label'] }}» حذف شود؟"
                                            class="btn btn-xs btn-outline-danger"
                                            style="padding:.2rem .45rem;font-size:.75rem;"
                                            title="حذف گروه">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                @if($panel === 'admin' && count($groups) > 0)
                <button type="button"
                        wire:click="clearAllVeteranGroups"
                        data-swal-confirm="همه گروه‌های ایثارگری این اقامتگاه پاک شوند؟"
                        class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>پاک کردن همه گروه‌ها
                </button>
                @else
                <span></span>
                @endif
                @if($canEditVeteranPolicy)
                <button type="submit" class="btn btn-primary btn-sm">ذخیره گروه‌ها</button>
                @endif
            </div>
        </div>
        {{-- <div class="alert alert-info small mt-3 mb-0">
            <strong>راهنما:</strong> سقف دوره یعنی حداکثر شب قابل استفاده در هر بازه (مثلاً ۳ شب در ۶ ماه).
            «شب/تکفل» یعنی ۶ شب به ازای هر نفر تحت تکفل. تعداد جلسات رایگان هفتگی در تب «تخفیف خدمات» تنظیم می‌شود.
        </div> --}}
    </form>

    @if($canEditVeteranPolicy)
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">افزودن گروه ایثارگری جدید</div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">نام گروه</label>
                    <input type="text" wire:model="newGroupLabel" class="form-control form-control-sm" placeholder="مثلاً: جانبازان ۸۰ درصد">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">تخفیف اقامت ٪</label>
                    <input type="number" wire:model="newGroupAccommodationDiscount" min="0" max="100" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="addCustomGroup" class="btn btn-success btn-sm w-100">افزودن گروه</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif

    @if($tab === 'services')
    <form wire:submit="saveServices">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">خدمات پیش‌فرض (dropdown رزرو دستی)</div>
            <div class="card-body p-0">
                @foreach($services as $service)
                <div class="border-bottom" wire:key="svc-block-{{ $service['key'] }}">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>نام خدمت</th>
                                    <th style="width:60px">فعال</th>
                                    @if($panel === 'admin')
                                    <th style="width:50px"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" wire:model="services.{{ $service['key'] }}.name" class="form-control form-control-sm">
                                        <div class="text-muted" style="font-size:.7rem">{{ $service['key'] }}</div>
                                    </td>
                                    <td class="text-center"><input type="checkbox" wire:model="services.{{ $service['key'] }}.is_active" class="form-check-input"></td>
                                    @if($panel === 'admin')
                                    <td class="text-center">
                                        <button type="button"
                                                wire:click="removeService({{ $service['id'] }})"
                                                data-swal-confirm="خدمت «{{ $service['name'] }}» حذف شود؟"
                                                class="btn btn-xs btn-outline-danger"
                                                style="padding:.2rem .45rem;font-size:.75rem;"
                                                title="حذف خدمت">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <x-veteran-policy.service-variants-section :service="$service" />
                </div>
                @endforeach
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                @if($panel === 'admin' && count($services) > 0)
                <button type="button"
                        wire:click="clearAllServices"
                        data-swal-confirm="همه خدمات این اقامتگاه پاک شوند؟"
                        class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>پاک کردن همه خدمات
                </button>
                @else
                <span></span>
                @endif
                @if($canEditVeteranPolicy)
                <button type="submit" class="btn btn-primary btn-sm">ذخیره خدمات و انواع</button>
                @endif
            </div>
        </div>
        <div class="alert alert-info small mb-3">
            <strong>راهنما:</strong> خدمت والد فقط عنوان کلی است (مثل «استخر» یا «رستوران»).
            قیمت در <strong>انواع زیرمجموعه</strong> تعریف می‌شود؛ تخفیف ایثارگری در تب «تخفیف خدمات» روی خدمت والد تنظیم می‌شود.
        </div>
    </form>

    @if($canEditVeteranPolicy)
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">افزودن خدمت جدید</div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label small">نام خدمت</label>
                    <input type="text" wire:model="newServiceName" class="form-control form-control-sm" placeholder="مثلاً: پارکینگ">
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="addCustomService" class="btn btn-success btn-sm w-100">افزودن</button>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-2">پس از افزودن، انواع و قیمت هر نوع را در بخش همان خدمت تعریف کنید.</p>
        </div>
    </div>
    @endif
    @endif

    @if($tab === 'matrix')
    <form wire:submit="saveDiscountMatrix">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">درصد تخفیف هر خدمت بر اساس گروه</div>
            <div class="card-body p-0">
                <x-veteran-policy.discount-matrix-table
                    :groups="$groups"
                    :services="$services"
                    :discount-matrix="$discountMatrix"
                    service-ref-field="id"
                />
            </div>
            <div class="card-footer bg-white text-end">
                @if($canEditVeteranPolicy)
                <button type="submit" class="btn btn-primary btn-sm">ذخیره ماتریس تخفیف</button>
                @endif
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            <strong>حالت عادی:</strong> فقط <em>درصد تخفیف همیشگی</em> را وارد کنید.
            <strong>حالت پله‌ای:</strong> با تیک «پله‌ای» جزئیات هفتگی (رایگان، مبلغ ثابت، درصد) را تنظیم کنید.
        </div>
    </form>
    @endif
</div>
