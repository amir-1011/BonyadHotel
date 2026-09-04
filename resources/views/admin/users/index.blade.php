<div>

<div class="card shadow-sm">
    <div class="ta-list-chrome admin-users-list-chrome">
            <ul class="nav nav-tabs mb-0 admin-users-section-tabs">
            <li class="nav-item">
                <button type="button" wire:click="setSection('all')" class="nav-link py-1 px-2 small {{ $section === 'all' ? 'active' : '' }}">
                    <i class="bi bi-grid me-1"></i>همه
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setSection('users')" class="nav-link py-1 px-2 small {{ $section === 'users' ? 'active' : '' }}">
                    <i class="bi bi-people me-1"></i>مهمان‌ها
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setSection('personnel')" class="nav-link py-1 px-2 small {{ $section === 'personnel' ? 'active' : '' }}">
                    <i class="bi bi-person-badge me-1"></i>پرسنل
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setSection('employers')" class="nav-link py-1 px-2 small {{ $section === 'employers' ? 'active' : '' }}">
                    <i class="bi bi-building me-1"></i>ادارات
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setSection('beneficiaries')" class="nav-link py-1 px-2 small {{ $section === 'beneficiaries' ? 'active' : '' }}">
                    <i class="bi bi-person-heart me-1"></i>ذی‌نفعان
                </button>
            </li>
            </ul>
            <div class="d-flex align-items-center gap-2 admin-users-search">
                <div class="flex-grow-1" style="min-width:0;">
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
            </div>
        <div class="ta-page-toolbar admin-users-toolbar">
            <a href="{{ route('admin.users.export', $exportQuery) }}" class="btn btn-sm btn-success">
                <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
                @if($hasActiveFilters)
                <span class="badge bg-white text-success ms-1">فیلترشده</span>
                @endif
            </a>
            <a wire:navigate href="{{ route('admin.users.create-host') }}" class="btn btn-sm btn-success">
                <i class="bi bi-person-plus me-1"></i>افزودن کاربر
            </a>
            <button type="button" wire:click="openEmployerModal" class="btn btn-sm btn-success">
                <i class="bi bi-building-add me-1"></i>افزودن کارفرما
            </button>
            <button type="button" wire:click="openBeneficiaryModal" class="btn btn-sm btn-success">
                <i class="bi bi-person-heart me-1"></i>افزودن ذینفع
            </button>
            <x-tutorial-videos
                variant="inline"
                :videos="[['label' => 'ساختن کاربر', 'file' => 'ساختن میزبان.mp4']]"
            />
        </div>
    </div>

    @if($section === 'personnel' && count($personnelTabOptions) > 0)
    <div class="px-3 pb-2">
        <ul class="nav nav-tabs mb-0 flex-wrap admin-users-personnel-tabs">
            @foreach($personnelTabOptions as $option)
            <li class="nav-item" wire:key="personnel-tab-{{ $option['value'] }}">
                <button type="button"
                        wire:click="setRoleTab({{ \Illuminate\Support\Js::from($option['value']) }})"
                        class="nav-link py-1 px-2 small {{ $role === $option['value'] ? 'active' : '' }}">
                    @if($option['value'] === \App\Support\AdminUserRoleFilterCatalog::ALL_PERSONNEL)
                    <i class="bi bi-people-fill me-1"></i>
                    @endif
                    {{ $option['label'] }}
                </button>
            </li>
            @endforeach
        </ul>
    </div>
    @elseif($section === 'personnel')
    <div class="alert alert-info small py-1 px-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>پرسنلی برای نمایش یافت نشد.
    </div>
    @endif

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
            @if($groupByProvince)
                @forelse($provinceGroups as $provinceGroup)
                <tbody
                    x-data="{
                        storageKey: @js('adminUsersProv:' . $section . ':' . $provinceGroup['key']),
                        sectionExpanded: sessionStorage.getItem(@js('adminUsersProv:' . $section . ':' . $provinceGroup['key'])) !== '0',
                        toggleSection() {
                            this.sectionExpanded = !this.sectionExpanded;
                            sessionStorage.setItem(this.storageKey, this.sectionExpanded ? '1' : '0');
                        }
                    }"
                    wire:key="admin-users-province-{{ $section }}-{{ $provinceGroup['key'] }}"
                >
                    <tr class="table-secondary admin-users-province-header">
                        <td class="col-index"></td>
                        <td colspan="8">
                            <div
                                class="d-flex align-items-center gap-2"
                                role="button"
                                @click="toggleSection()"
                                style="cursor:pointer"
                            >
                                <i class="bi bi-chevron-left text-muted admin-users-province-chevron" :class="{ 'is-expanded': sectionExpanded }"></i>
                                <span class="fw-semibold small">
                                    <i class="bi bi-geo-alt me-1 text-primary"></i>
                                    {{ $provinceGroup['province_name'] }}
                                    @if($provinceGroup['province_code'])
                                    <span class="text-muted" dir="ltr">({{ $provinceGroup['province_code'] }})</span>
                                    @endif
                                </span>
                                <span class="badge bg-light text-dark border">{{ count($provinceGroup['users']) }} کاربر</span>
                            </div>
                        </td>
                    </tr>
                    @foreach($provinceGroup['users'] as $user)
                    @include('admin.users._table-row', ['user' => $user, 'collapsible' => true])
                    @endforeach
                </tbody>
                @empty
                <tbody>
                    <tr><td colspan="9" class="text-center text-muted py-3">کاربری یافت نشد</td></tr>
                </tbody>
                @endforelse
            @else
            <tbody>
                @forelse($users as $user)
                @include('admin.users._table-row', ['user' => $user])
                @empty
                <tr><td colspan="9" class="text-center text-muted py-3">کاربری یافت نشد</td></tr>
                @endforelse
            </tbody>
            @endif
        </table>
    </div>
    @if($groupByProvince)
    <div class="card-footer bg-white py-2 small text-muted">{{ \App\Support\PdfPersian::toPersianDigits(number_format($totalUsers)) }} کاربر در {{ count($provinceGroups) }} استان</div>
    @elseif($users->hasPages())
    <div class="card-footer bg-white py-2">{{ $users->links() }}</div>
    @endif
</div>

<x-accounting.add-employer-modal
    :provinces="$provinces"
    :show-add-employer="$showAddEmployer"
    province-hint="استان مورد نظر را برای صدور کد حسابداری انتخاب کنید."
    save-label="ذخیره"
/>
<x-accounting.add-beneficiary-modal
    :provinces="$provinces"
    :show-add-beneficiary="$showAddBeneficiary"
    province-hint="استان مورد نظر را برای صدور کد حسابداری انتخاب کنید."
    info-message="ذینفعان در کل سامانه یکپارچه هستند. پس از ثبت، در صورت امکان به‌عنوان کاربر سیستم نیز ثبت می‌شوند."
    save-label="ذخیره"
/>

<style>
    .admin-users-list-chrome {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "search toolbar"
            "tabs toolbar";
        gap: 8px;
        align-items: start;
    }

    .admin-users-list-chrome .admin-users-section-tabs {
        grid-area: tabs;
        flex: none !important;
        width: 100% !important;
        align-self: start;
    }

    .admin-users-list-chrome .admin-users-search {
        grid-area: search;
        min-width: 0;
    }

    .admin-users-list-chrome .admin-users-toolbar {
        grid-area: toolbar;
        margin: 0 !important;
        align-self: start;
    }

    .admin-users-section-tabs {
        display: flex;
        flex-wrap: wrap;
    }

    .admin-users-section-tabs .nav-item {
        flex: 1 1 0;
        min-width: 5.5rem;
    }

    .admin-users-section-tabs .nav-link {
        width: 100%;
        text-align: center;
        white-space: nowrap;
    }

    .admin-users-personnel-tabs {
        width: 100%;
    }

    .admin-users-province-chevron {
        display: inline-block;
        transition: transform .25s ease;
    }

    .admin-users-province-chevron.is-expanded {
        transform: rotate(-90deg);
    }

    .admin-users-toolbar {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
        width: min(100%, 18rem);
    }

    .admin-users-toolbar > .btn {
        width: 100%;
        justify-content: center;
    }

    .admin-users-toolbar > :not(.btn) {
        grid-column: 1 / -1;
    }

    @media (max-width: 640px) {
        .admin-users-list-chrome {
            grid-template-columns: 1fr;
            grid-template-areas:
                "search"
                "tabs"
                "toolbar";
        }

        .admin-users-toolbar {
            width: 100% !important;
        }
    }
</style>

</div>
