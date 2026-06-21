<div>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h5 class="fw-bold mb-1">تنظیمات ایثارگری و خدمات</h5>
            <p class="text-muted small mb-0">مدیریت درصد تخفیف اقامت، سقف استفاده و تخفیف هر خدمت بر اساس گروه ایثارگری</p>
        </div>
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
                                <th>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        گروه
                                        <x-admin.column-help title="گروه ایثارگری">
                                            <strong>نام نمایشی</strong> گروه در فرم رزرو دستی، پروفایل کاربر و گزارش‌ها.
                                            <ul class="mt-2">
                                                <li>کلید سیستمی (زیر نام) فقط برای شناسایی داخلی است و قابل تغییر نیست.</li>
                                                <li>هر ردیف یک دسته از مشمولین (مثل جانباز ۷۰٪ یا همسر شهید) را مشخص می‌کند.</li>
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
                                        شب/تکفل
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
                                            توضیح متنی برای ادمین و میزبان درباره قوانین استفاده این گروه.
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
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">ذخیره گروه‌ها</button>
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            <strong>راهنما:</strong> سقف دوره یعنی حداکثر شب قابل استفاده در هر بازه (مثلاً ۳ شب در ۶ ماه).
            «شب/تکفل» یعنی ۶ شب به ازای هر نفر تحت تکفل. تعداد جلسات رایگان هفتگی در تب «تخفیف خدمات» تنظیم می‌شود.
        </div>
    </form>

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">افزودن گروه ایثارگری جدید</div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        نام گروه
                        <x-admin.column-help title="گروه جدید">
                            نام نمایشی گروه مشمول جدید. پس از افزودن، برای همه خدمات ردیف تخفیف با مقدار پیش‌فرض هر خدمت ساخته می‌شود.
                            <ul class="mt-2">
                                <li>کلید سیستمی به‌صورت خودکار با پیشوند <code>custom_group_</code> ساخته می‌شود.</li>
                                <li>سقف استفاده و تخفیف خدمات را پس از افزودن در همین صفحه و تب «تخفیف خدمات» تنظیم کنید.</li>
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
                    <button type="button" wire:click="addCustomGroup" class="btn btn-success btn-sm w-100">افزودن گروه</button>
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
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        نام خدمت
                                        <x-admin.column-help title="نام خدمت">
                                            عنوان نمایشی خدمت در فرم رزرو دستی و فاکتور.
                                            <ul class="mt-2">
                                                <li>کلید سیستمی (زیر نام) برای اتصال به ماتریس تخفیف است.</li>
                                                <li>خدمات جدید با پیشوند <code>custom_</code> ساخته می‌شوند.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:120px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        قیمت پیش‌فرض
                                        <x-admin.column-help title="قیمت پیش‌فرض">
                                            مبلغ پایه هر واحد خدمت (تومان) هنگام افزودن به رزرو دستی.
                                            <ul class="mt-2">
                                                <li>میزبان/ادمین می‌تواند در هر رزرو مبلغ را تغییر دهد.</li>
                                                <li>برای خدمات رایگان یا توافقی، ۰ بگذارید.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:90px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        تخفیف پیش‌فرض
                                        <x-admin.column-help title="تخفیف پیش‌فرض">
                                            درصد تخفیف پیش‌فرض این خدمت وقتی برای گروهی قانون اختصاصی تعریف نشده باشد.
                                            <ul class="mt-2">
                                                <li>در تب «تخفیف خدمات» می‌توانید برای هر گروه مقدار جدا تنظیم کنید.</li>
                                                <li>محدوده: ۰ تا ۱۰۰.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th class="d-none" style="width:95px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        حداقل
                                        <x-admin.column-help title="حداقل تخفیف">
                                            کف درصد تخفیف هنگام <strong>تغییر دستی</strong> تخفیف در رزرو.
                                            <ul class="mt-2">
                                                <li>اگر خالی باشد، محدودیت پایینی اعمال نمی‌شود.</li>
                                                <li>برای خدمات ورزشی معمولاً ۵۰٪ تنظیم می‌شود.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th class="d-none" style="width:95px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        حداکثر
                                        <x-admin.column-help title="حداکثر تخفیف">
                                            سقف درصد تخفیف هنگام <strong>تغییر دستی</strong> تخفیف در رزرو.
                                            <ul class="mt-2">
                                                <li>اگر خالی باشد، محدودیت بالایی اعمال نمی‌شود.</li>
                                                <li>برای خدمات ورزشی معمولاً ۸۰٪ تنظیم می‌شود.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:80px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        جلسه رایگان
                                        <x-admin.column-help title="پشتیبانی جلسه رایگان">
                                            مشخص می‌کند آیا این خدمت می‌تواند <strong>جلسه رایگان هفتگی</strong> داشته باشد.
                                            <ul class="mt-2">
                                                <li>برای استخر، بدنسازی و سالن فعال کنید.</li>
                                                <li>در تب «تخفیف خدمات» تیک «رایگان» برای هر گروه جداگانه تنظیم می‌شود.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                <th style="width:60px">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        فعال
                                        <x-admin.column-help title="وضعیت خدمت">
                                            اگر غیرفعال باشد:
                                            <ul class="mt-2">
                                                <li>در dropdown خدمات رزرو دستی نمایش داده نمی‌شود.</li>
                                                <li>ستون آن در ماتریس تخفیف همچنان قابل مشاهده است تا بتوانید دوباره فعال کنید.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $i => $service)
                            <tr wire:key="svc-{{ $service['id'] }}">
                                <td>
                                    <input type="text" wire:model="services.{{ $i }}.name" class="form-control form-control-sm">
                                    <div class="text-muted" style="font-size:.7rem">{{ $service['key'] }}</div>
                                </td>
                                <td><input type="number" wire:model="services.{{ $i }}.default_price" min="0" class="form-control form-control-sm"></td>
                                <td><input type="number" wire:model="services.{{ $i }}.default_discount" min="0" max="100" class="form-control form-control-sm"></td>
                                <td class="d-none"><input type="number" wire:model="services.{{ $i }}.min_discount" min="0" max="100" class="form-control form-control-sm" placeholder="—"></td>
                                <td class="d-none"><input type="number" wire:model="services.{{ $i }}.max_discount" min="0" max="100" class="form-control form-control-sm" placeholder="—"></td>
                                <td class="text-center"><input type="checkbox" wire:model="services.{{ $i }}.supports_free_sessions" class="form-check-input"></td>
                                <td class="text-center"><input type="checkbox" wire:model="services.{{ $i }}.is_active" class="form-check-input"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">ذخیره خدمات</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">افزودن خدمت جدید</div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        نام خدمت
                        <x-admin.column-help title="نام خدمت جدید">
                            نام فارسی خدمتی که به فهرست اضافه می‌شود. پس از افزودن، برای همه گروه‌های ایثارگری ردیف تخفیف با مقدار ۰٪ ساخته می‌شود.
                        </x-admin.column-help>
                    </label>
                    <input type="text" wire:model="newServiceName" class="form-control form-control-sm" placeholder="مثلاً: پارکینگ">
                </div>
                <div class="col-md-3">
                    <label class="form-label small d-inline-flex align-items-center gap-1">
                        قیمت پیش‌فرض
                        <x-admin.column-help title="قیمت خدمت جدید">
                            مبلغ پایه (تومان) که هنگام انتخاب این خدمت در رزرو دستی پیشنهاد می‌شود.
                        </x-admin.column-help>
                    </label>
                    <input type="number" wire:model="newServicePrice" min="0" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="addCustomService" class="btn btn-success btn-sm w-100">افزودن</button>
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
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <span class="d-inline-flex align-items-center gap-1">
                                        گروه \ خدمت
                                        <x-admin.column-help title="ماتریس تخفیف">
                                            هر سلول درصد تخفیف یک خدمت را برای یک گروه ایثارگری مشخص می‌کند.
                                            <ul class="mt-2">
                                                <li>ردیف = گروه مشمول (جانباز، شهید، …)</li>
                                                <li>ستون = نوع خدمت (استخر، سالن، …)</li>
                                                <li>برای خدمات ورزشی، تیک «رایگان» جلسات هفتگی رایگان را فعال می‌کند.</li>
                                            </ul>
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                @foreach($services as $service)
                                <th class="text-center" style="min-width:100px">
                                    <span class="d-inline-flex align-items-center justify-content-center gap-1 flex-wrap">
                                        {{ $service['name'] }}
                                        <x-admin.column-help :title="$service['name']">
                                            @if($service['supports_free_sessions'])
                                                خدمت ورزشی — با فعال‌کردن تیک «رایگان» می‌توانید تعداد جلسات رایگان هفتگی را مشخص کنید.
                                                <ul class="mt-2">
                                                    <li>درصد: تخفیف روی مبلغ خدمت برای گروه‌هایی که رایگان نیستند.</li>
                                                    <li>تیک رایگان: برای جانباز ۷۰٪ معمولاً فعال است (مثلاً ۳ جلسه در هفته).</li>
                                                </ul>
                                            @else
                                                درصد تخفیف این خدمت برای هر گروه ایثارگری.
                                                <ul class="mt-2">
                                                    <li>محدوده: ۰ تا ۱۰۰.</li>
                                                    <li>تخفیف روی مبلغ ثبت‌شده در رزرو اعمال می‌شود.</li>
                                                </ul>
                                            @endif
                                        </x-admin.column-help>
                                    </span>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $gi => $group)
                            <tr wire:key="mx-{{ $group['key'] }}">
                                <td class="fw-semibold small">{{ $group['label'] }}</td>
                                @foreach($services as $service)
                                @php $sid = $service['id']; @endphp
                                <td class="text-center" wire:key="mx-{{ $group['key'] }}-{{ $sid }}">
                                    <input type="number"
                                           wire:model="discountMatrix.{{ $group['key'] }}.{{ $sid }}.discount_percentage"
                                           min="0" max="100"
                                           class="form-control form-control-sm mb-1" style="width:70px;margin:0 auto">
                                    @if($service['supports_free_sessions'])
                                    <label class="d-flex align-items-center justify-content-center gap-1" style="font-size:.7rem">
                                        <input type="checkbox"
                                               wire:model.live="discountMatrix.{{ $group['key'] }}.{{ $sid }}.free_sessions_eligible"
                                               class="form-check-input m-0">
                                        <span class="d-inline-flex align-items-center gap-1">
                                            رایگان
                                            <x-admin.column-help title="جلسه رایگان">
                                                با فعال‌کردن این تیک، تعداد جلسات رایگان هفتگی را وارد کنید. هزینه این خدمت تا آن سقف صفر محاسبه می‌شود.
                                            </x-admin.column-help>
                                        </span>
                                    </label>
                                    @if($discountMatrix[$group['key']][$sid]['free_sessions_eligible'] ?? false)
                                    <div class="mt-1">
                                        <input type="number"
                                               wire:model="discountMatrix.{{ $group['key'] }}.{{ $sid }}.weekly_free_sessions"
                                               min="0" max="21"
                                               class="form-control form-control-sm"
                                               style="width:70px;margin:0 auto"
                                               placeholder="تعداد">
                                        <div class="text-muted" style="font-size:.65rem">جلسه/هفته</div>
                                    </div>
                                    @endif
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">ذخیره ماتریس تخفیف</button>
            </div>
        </div>
        <div class="alert alert-info small mt-3 mb-0">
            برای خدمات ورزشی (استخر، بدنسازی، سالن): با فعال‌کردن تیک «رایگان»، تعداد جلسات رایگان هفتگی را <strong>برای هر خدمت جداگانه</strong> وارد کنید (مثلاً ۳ جلسه استخر و ۲ جلسه بدنسازی برای جانباز ۷۰٪).
        </div>
    </form>
    @endif
</div>
