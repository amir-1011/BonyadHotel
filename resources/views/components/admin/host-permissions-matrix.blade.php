@props([
    'catalog' => [],
    'formState' => [],
])

@php
    use App\Support\HostPermissions;

    $actions = HostPermissions::ACTIONS;
@endphp

<div {{ $attributes->merge(['class' => 'host-permissions-matrix']) }}>
    @error('hostPermissionForm')
        <div class="alert alert-danger py-1 small">{{ $message }}</div>
    @enderror

    <p class="text-muted small mb-3">
        برای هر صفحه از پنل میزبان، سطح دسترسی را مشخص کنید:
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
                    <th style="min-width: 220px">بخش / صفحه</th>
                    @foreach($actions as $action)
                        <th class="text-center" style="width: 72px">{{ HostPermissions::actionLabel($action) }}</th>
                    @endforeach
                    <th class="text-center" style="width: 64px">همه</th>
                </tr>
            </thead>
            <tbody>
                @foreach($catalog as $moduleKey => $module)
                    <tr class="table-secondary">
                        <td colspan="{{ count($actions) + 2 }}">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <button
                                    type="button"
                                    class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold text-body"
                                    wire:click="toggleHostPermissionModule('{{ $moduleKey }}')"
                                >
                                    <i class="bi bi-{{ $module['icon'] }} me-1 text-primary"></i>
                                    {{ $module['label'] }}
                                </button>
                                <span class="badge bg-light text-muted border">
                                    {{ HostPermissions::moduleHasAnyEnabled($formState, $moduleKey) ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </div>
                            <div class="small text-muted">{{ $module['description'] }}</div>
                        </td>
                    </tr>

                    @foreach($module['pages'] as $pageKey => $page)
                        <tr wire:key="host-perm-{{ $pageKey }}">
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
                @endforeach
            </tbody>
        </table>
    </div>
</div>
