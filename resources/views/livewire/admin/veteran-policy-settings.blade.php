<div wire:key="veteran-policy-{{ $filterKey }}">
    <x-veteran-policy.discount-matrix-styles />

    <div class="card shadow-sm mb-3">
        <div class="ta-list-chrome">
            <div class="small text-muted min-w-0 flex-grow-1">
                @if($isAllAccommodationsSelected)
                    مدیریت درصد تخفیف اقامت، سقف استفاده و تخفیف هر خدمت — تغییرات این صفحه روی <strong>همه {{ $accommodationCount }} اقامتگاه</strong> اعمال می‌شود.
                @elseif($scopedAccommodationCount === 1)
                    @php
                        $singleAcc = collect($dashboardAccommodationOptions)->firstWhere('id', $scopedAccommodationIds[0] ?? null);
                    @endphp
                    تنظیمات ایثارگری برای اقامتگاه <strong>{{ $singleAcc['name'] ?? 'انتخاب‌شده' }}</strong> — تغییرات فقط روی این اقامتگاه اعمال می‌شود.
                @else
                    تنظیمات ایثارگری برای <strong>{{ $scopedAccommodationCount }} اقامتگاه</strong> انتخاب‌شده — تغییرات فقط روی اقامتگاه‌های فیلترشده اعمال می‌شود.
                @endif
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <x-tutorial-videos variant="inline" :videos="[
                    ['label' => 'قوانین ایثارگری', 'file' => 'قوانین ایثارگری.mp4'],
                ]" />
                @if($this->showDashboardAccommodationFilter())
                    @include('components.dashboard.accommodation-filter', [
                        'hint' => 'تغییرات پس از «اعمال» روی تنظیمات ایثارگری اعمال می‌شود',
                    ])
                @endif
            </div>
        </div>
    </div>

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
                                <th>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        گروه
                                        <x-admin.column-help title="گروه ایثارگری">
                                            <strong>نام نمایشی</strong> گروه در فرم رزرو دستی، پروفایل کاربر و گزارش‌ها.
                                            <ul class="mt-2">
                                                <li>کلید سیستمی (زیر نام) فقط برای شناسایی داخلی است و قابل تغییر نیست.</li>
                                                <li>هر ردیف یک دسته از مشمولین (مثل جانباز ۷۰٪ یا همسر شهید) را مشخص می‌کند.</li>
                                                <li>زیر هر گروه، اقامتگاه‌هایی که این سیاست را دارند نمایش داده می‌شود.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:90px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        تخفیف اقامت ٪
                                        <x-admin.column-help title="تخفیف اقامت">
                                            درصد تخفیف روی <strong>هزینه اقامت</strong> (اتاق) برای این گروه.
                                            <ul class="mt-2">
                                                <li>مثال: ۷۰ یعنی مهمان فقط ۳۰٪ مبلغ اقامت را می‌پردازد.</li>
                                                <li>این تخفیف جدا از تخفیف خدمات جانبی (استخر، سالن و …) است.</li>
                                                <li>محدوده مجاز: ۰ تا ۱۰۰.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:90px" class="d-none">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        سقف کل/نفر
                                        <x-admin.column-help title="شب به ازای هر نفر تحت تکفل">
                                            تعداد شب اقامت مجاز <strong>به ازای هر نفر</strong> (شامل خود جانباز/مشمول و افراد تحت تکفل).
                                            <ul class="mt-2">
                                                <li>سقف کل = این عدد × تعداد مهمان در رزرو.</li>
                                                <li>مثال: ۶ شب/تکفل با ۳ مهمان → حداکثر ۱۸ شب در کل.</li>
                                                <li>سیستم هنگام رزرو دستی مصرف قبلی را با کد ملی/کاربر بررسی می‌کند.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:90px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        سقف دوره
                                        <x-admin.column-help title="سقف دوره">
                                            حداکثر تعداد شب قابل رزرو در <strong>هر بازه زمانی</strong> (مستقل از سقف کل).
                                            <ul class="mt-2">
                                                <li>مثال: ۳ شب یعنی در هر دوره حداکثر ۳ شب می‌توان رزرو کرد.</li>
                                                <li>اگر رزرو از این سقف بیشتر شود، سیستم اجازه ثبت نمی‌دهد.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:90px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        دوره (ماه)
                                        <x-admin.column-help title="طول دوره">
                                            طول بازه زمانی (به ماه) برای محاسبه <strong>سقف دوره</strong>.
                                            <ul class="mt-2">
                                                <li>مثال: ۶ ماه + سقف ۳ شب → در ۶ ماه گذشته حداکثر ۳ شب استفاده شده باشد.</li>
                                                <li>رزروهای لغوشده در محاسبه لحاظ نمی‌شوند.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        یادداشت
                                        <x-admin.column-help title="یادداشت سقف استفاده">
                                            توضیح متنی برای ادمین و کاربر درباره قوانین استفاده این گروه.
                                            <ul class="mt-2">
                                                <li>در خلاصه سقف استفاده هنگام رزرو دستی نمایش داده می‌شود.</li>
                                                <li>مثال: «۶ شب به ازای هر نفر تحت تکفل (سقف ۳ شب در هر ۶ ماه)»</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:60px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        فعال
                                        <x-admin.column-help title="وضعیت گروه">
                                            اگر غیرفعال باشد:
                                            <ul class="mt-2">
                                                <li>این گروه در لیست انتخاب رزرو دستی نمایش داده نمی‌شود.</li>
                                                <li>تخفیف‌های قبلی ثبت‌شده روی رزروهای قدیمی تغییر نمی‌کند.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $i => $group)
                            <tr wire:key="grp-{{ $filterKey }}-{{ $group['key'] }}">
                                <td>
                                    <input type="text" wire:model="groups.{{ $i }}.label" class="form-control form-control-sm">
                                    <div class="text-muted" style="font-size:.7rem">{{ $group['key'] }}</div>
                                    <x-veteran-policy.accommodation-badges
                                        :accommodations="$groupAccommodationsByKey[$group['key']] ?? []"
                                    />
                                </td>
                                <td><x-veteran-policy.accommodation-discount-tiers :group-index="$i" :group="$group" /></td>
                                <td class="d-none"><input type="number" wire:model="groups.{{ $i }}.nights_per_dependent" min="1" class="form-control form-control-sm"></td>
                                <td><input type="number" wire:model="groups.{{ $i }}.max_nights_per_period" min="1" class="form-control form-control-sm"></td>
                                <td><input type="number" wire:model="groups.{{ $i }}.period_months" min="1" class="form-control form-control-sm"></td>
                                <td><input type="text" wire:model="groups.{{ $i }}.usage_notes" class="form-control form-control-sm" placeholder="توضیح سقف استفاده"></td>
                                <td class="text-center"><input type="checkbox" wire:model="groups.{{ $i }}.is_active" class="form-check-input"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    @if($isAllAccommodationsSelected)
                        ذخیره گروه‌ها (همه اقامتگاه‌ها)
                    @else
                        ذخیره گروه‌ها ({{ $scopedAccommodationCount }} اقامتگاه)
                    @endif
                </button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">افزودن گروه ایثارگری جدید</div>
        <div class="card-body">
            @if(!$isAllAccommodationsSelected)
            <div class="alert alert-light border small py-2 mb-3">
                <i class="bi bi-building me-1"></i>
                این گروه فقط به <strong>{{ $scopedAccommodationCount }} اقامتگاه</strong> انتخاب‌شده در فیلتر بالا اضافه می‌شود.
            </div>
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        نام گروه
                        <x-admin.column-help title="گروه جدید">
                            نام نمایشی گروه مشمول جدید. پس از افزودن، برای همه خدمات ردیف تخفیف با مقدار پیش‌فرض هر خدمت ساخته می‌شود.
                            <ul class="mt-2">
                                <li>کلید سیستمی به‌صورت خودکار با پیشوند <code>custom_group_</code> ساخته می‌شود.</li>
                                <li>سقف استفاده و تخفیف خدمات را پس از افزودن در همین صفحه و تب «تخفیف خدمات» تنظیم کنید.</li>
                                <li>با فیلتر اقامتگاه، می‌توانید گروه را فقط به اقامتگاه‌های مشخص نسبت دهید.</li>
                            </ul>
                        </x-admin.column-help>
                    </label>
                    <input type="text" wire:model="newGroupLabel" class="form-control form-control-sm" placeholder="مثلاً: جانبازان ۸۰ درصد">
                </div>
                <div class="col-md-3">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        تخفیف اقامت ٪
                        <x-admin.column-help title="تخفیف اقامت گروه جدید">
                            درصد تخفیف اولیه روی هزینه اقامت. بعداً در جدول بالا قابل ویرایش است.
                        </x-admin.column-help>
                    </label>
                    <input type="number" wire:model="newGroupAccommodationDiscount" min="0" max="100" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="addCustomGroup" class="btn btn-success btn-sm w-100" @disabled($scopedAccommodationCount === 0)>افزودن گروه</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($tab === 'services')
    <form wire:submit="saveServices">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">خدمات پیش‌فرض (dropdown رزرو دستی)</div>
            <div class="card-body p-0">
                @foreach($services as $service)
                <div class="border-bottom" wire:key="svc-block-{{ $filterKey }}-{{ $service['key'] }}">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>نام خدمت</th>
                                    <th style="width:60px">فعال</th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" wire:model="services.{{ $service['key'] }}.name" class="form-control form-control-sm">
                                        <div class="text-muted" style="font-size:.7rem">{{ $service['key'] }}</div>
                                        <x-veteran-policy.accommodation-badges
                                            :accommodations="$serviceAccommodationsByKey[$service['key']] ?? []"
                                        />
                                    </td>
                                    <td class="text-center"><input type="checkbox" wire:model="services.{{ $service['key'] }}.is_active" class="form-check-input"></td>
                                    <td class="text-center">
                                        <button type="button"
                                                wire:click="removeService(@js($service['key']))"
                                                data-swal-confirm="خدمت «{{ $service['name'] }}» از {{ $isAllAccommodationsSelected ? 'همه اقامتگاه‌ها' : $scopedAccommodationCount . ' اقامتگاه انتخاب‌شده' }} حذف شود؟"
                                                class="btn btn-xs btn-outline-danger"
                                                style="padding:.2rem .45rem;font-size:.75rem;"
                                                title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <x-veteran-policy.service-variants-section
                        :service="$service"
                        :variant-accommodations-by-key="$variantAccommodationsByServiceKey[$service['key']] ?? []"
                    />
                </div>
                @endforeach
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    @if($isAllAccommodationsSelected)
                        ذخیره خدمات و انواع (همه اقامتگاه‌ها)
                    @else
                        ذخیره خدمات و انواع ({{ $scopedAccommodationCount }} اقامتگاه)
                    @endif
                </button>
            </div>
        </div>
        <div class="alert alert-info small mb-3">
            قیمت فقط در <strong>انواع زیرمجموعه</strong> تعریف می‌شود. تخفیف ایثارگری روی <strong>خدمت والد</strong> در تب «تخفیف خدمات» تنظیم می‌شود.
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">افزودن خدمت جدید</div>
        <div class="card-body">
            @if(!$isAllAccommodationsSelected)
            <div class="alert alert-light border small py-2 mb-3">
                <i class="bi bi-building me-1"></i>
                این خدمت فقط به <strong>{{ $scopedAccommodationCount }} اقامتگاه</strong> انتخاب‌شده در فیلتر بالا اضافه می‌شود.
            </div>
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        نام خدمت
                        <x-admin.column-help title="نام خدمت جدید">
                            عنوان کلی خدمت (مثل «استخر»). پس از افزودن، انواع و قیمت هر نوع را در بخش همان خدمت تعریف کنید.
                        </x-admin.column-help>
                    </label>
                    <input type="text" wire:model="newServiceName" class="form-control form-control-sm" placeholder="مثلاً: پارکینگ">
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="addCustomService" class="btn btn-success btn-sm w-100" @disabled($scopedAccommodationCount === 0)>افزودن</button>
                </div>
            </div>
        </div>
    </div>
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
                    service-ref-field="key"
                    :show-column-help="true"
                />
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    @if($isAllAccommodationsSelected)
                        ذخیره ماتریس تخفیف (همه اقامتگاه‌ها)
                    @else
                        ذخیره ماتریس تخفیف ({{ $scopedAccommodationCount }} اقامتگاه)
                    @endif
                </button>
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            <strong>حالت عادی:</strong> درصد تخفیف همیشگی.
            <strong>حالت پله‌ای:</strong> تیک «پله‌ای» → جزئیات هفتگی (رایگان، مبلغ ثابت، درصد).
        </div>
    </form>
    @endif
</div>
