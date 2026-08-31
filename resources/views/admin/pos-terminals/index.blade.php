<div class="ta-page">
    <script type="application/json" id="bnb-pos-terminal-provinces">
        @json($provinces->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values())
    </script>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form wire:submit="applyFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">استان</label>
                        <select wire:model="draftProvinceFilter" class="form-select form-select-sm">
                            <option value="">همه استان‌ها</option>
                            @foreach($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">جستجو</label>
                        <input type="search" wire:model="draftSearch" class="form-control form-control-sm" placeholder="شماره یا عنوان ترمینال">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                    </button>
                    @if($hasActiveFilters)
                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>پاک کردن
                    </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
            <span class="fw-semibold small">فهرست ترمینال‌ها</span>
            <button type="button"
                    data-bnb-pos-terminal-form="create"
                    data-default-province-id="{{ $provinceFilter }}"
                    class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>ترمینال جدید
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>استان</th>
                        <th>شماره ترمینال</th>
                        <th>عنوان</th>
                        <th>وضعیت</th>
                        <th class="text-end">تعداد تراکنش</th>
                        <th class="text-end">مجموع مبلغ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($terminals as $terminal)
                    <tr>
                        <td>{{ $terminal->province?->name }}</td>
                        <td><code dir="ltr">{{ $terminal->terminal_number }}</code></td>
                        <td>{{ $terminal->label ?: '—' }}</td>
                        <td>
                            @if($terminal->is_active)
                            <span class="badge bg-success-subtle text-success">فعال</span>
                            @else
                            <span class="badge bg-secondary-subtle text-secondary">غیرفعال</span>
                            @endif
                        </td>
                        <td class="text-end">{{ \App\Support\PdfPersian::toPersianDigits(number_format($terminal->transactions_count ?? 0)) }}</td>
                        <td class="text-end fw-semibold" dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format((int) ($terminal->transactions_total ?? 0))) }}</td>
                        <td class="text-end">
                            <button type="button"
                                    data-bnb-pos-terminal-form="edit"
                                    data-terminal-id="{{ $terminal->id }}"
                                    data-province-id="{{ $terminal->province_id }}"
                                    data-terminal-number="{{ $terminal->terminal_number }}"
                                    data-label="{{ $terminal->label ?? '' }}"
                                    data-is-active="{{ $terminal->is_active ? '1' : '0' }}"
                                    class="btn btn-sm btn-outline-primary">ویرایش</button>
                            <button type="button"
                                    wire:click="delete({{ $terminal->id }})"
                                    data-swal-confirm="این ترمینال حذف شود؟"
                                    data-swal-confirm-variant="delete"
                                    class="btn btn-sm btn-outline-danger">حذف</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">ترمینالی ثبت نشده است.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($terminals->hasPages())
        <div class="card-footer">{{ $terminals->links() }}</div>
        @endif
    </div>
</div>
