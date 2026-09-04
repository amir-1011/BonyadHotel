@props([
    'catalog' => [],
    'formState' => [],
])

@php
    use App\Support\HostPermissions;

    $actions = HostPermissions::ACTIONS;
    $colCount = count($actions) + 2;
@endphp

<div {{ $attributes->merge(['class' => 'host-permissions-matrix']) }}>
    <style>
        .host-permissions-matrix__table {
            width: 100%;
            table-layout: fixed;
        }
        .host-permissions-matrix__chevron {
            display: inline-block;
            transition: transform .25s ease;
        }
        .host-permissions-matrix__chevron.is-expanded {
            transform: rotate(-90deg);
        }
    </style>
    @error('hostPermissionForm')
        <div class="alert alert-danger py-1 small">{{ $message }}</div>
    @enderror

    <p class="text-muted small mb-3">
        برای هر صفحه از پنل کاربر، سطح دسترسی را مشخص کنید:
        <strong>مشاهده</strong> (لیست و جزئیات)،
        <strong>ایجاد</strong> (ثبت جدید)،
        <strong>ویرایش</strong> (تغییر اطلاعات)،
        <strong>حذف</strong> (حذف یا لغو).
        داده‌ها بر اساس اقامتگاه‌های انتخاب‌شده فیلتر می‌شوند.
    </p>

    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0 host-permissions-matrix__table">
            <thead class="table-light">
                <tr>
                    <th style="width: 38%">بخش / صفحه</th>
                    @foreach($actions as $action)
                        <th class="text-center">{{ HostPermissions::actionLabel($action) }}</th>
                    @endforeach
                    <th class="text-center" style="width: 64px">همه</th>
                </tr>
            </thead>
            @foreach($catalog as $moduleKey => $module)
            <tbody
                x-data="{
                    storageKey: @js('hostPermCollapse:' . $moduleKey),
                    sectionExpanded: sessionStorage.getItem(@js('hostPermCollapse:' . $moduleKey)) !== '0',
                    toggleSection() {
                        this.sectionExpanded = !this.sectionExpanded;
                        sessionStorage.setItem(this.storageKey, this.sectionExpanded ? '1' : '0');
                    }
                }"
                wire:key="host-perm-module-{{ $moduleKey }}"
            >
                <tr class="table-secondary">
                    <td colspan="{{ $colCount }}">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div
                                class="d-flex align-items-center gap-2 flex-grow-1"
                                role="button"
                                @click="toggleSection()"
                                style="cursor:pointer"
                            >
                                <i class="bi bi-chevron-left text-muted host-permissions-matrix__chevron" :class="{ 'is-expanded': sectionExpanded }"></i>
                                <span class="fw-semibold small">
                                    <i class="bi bi-{{ $module['icon'] }} me-1 text-primary"></i>
                                    {{ $module['label'] }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="badge border {{ HostPermissions::moduleHasAnyEnabled($formState, $moduleKey) ? 'bg-success-subtle text-success' : 'bg-light text-muted' }}"
                                wire:click.stop="toggleHostPermissionModule('{{ $moduleKey }}')"
                                title="فعال / غیرفعال کردن همه دسترسی‌های این بخش"
                            >
                                {{ HostPermissions::moduleHasAnyEnabled($formState, $moduleKey) ? 'فعال' : 'غیرفعال' }}
                            </button>
                        </div>
                        <div class="small text-muted" role="button" @click="toggleSection()" style="cursor:pointer">{{ $module['description'] }}</div>
                    </td>
                </tr>

                @foreach($module['pages'] as $pageKey => $page)
                    <tr wire:key="host-perm-{{ $pageKey }}" x-show="sectionExpanded" x-cloak>
                        <td>
                            <div class="ps-3">
                                <div class="fw-semibold small">{{ $page['label'] }}</div>
                                <div class="text-muted" style="font-size: .75rem">{{ $page['description'] }}</div>
                            </div>
                        </td>
                        @foreach($actions as $action)
                            @php
                                $allowed = in_array($action, $page['actions'], true);
                                $fieldKey = HostPermissions::formKey($pageKey, $action);
                            @endphp
                            <td class="text-center">
                                @if($allowed)
                                    <input
                                        type="checkbox"
                                        class="form-check-input m-0"
                                        wire:model.live="hostPermissionForm.{{ $fieldKey }}"
                                    >
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-center">
                            <button
                                type="button"
                                class="btn btn-outline-secondary btn-sm py-0 px-2"
                                wire:click="toggleHostPermissionPage('{{ $pageKey }}')"
                                title="تغییر همه دسترسی‌های این صفحه"
                            >
                                <i class="bi bi-check2-all"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @endforeach
        </table>
    </div>
</div>
