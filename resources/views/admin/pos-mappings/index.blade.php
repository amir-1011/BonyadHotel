<div class="ta-page">
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form wire:submit="save">
                <div class="fw-semibold small mb-3">
                    {{ $editingId ? 'ویرایش مپینگ پوز' : 'ثبت مپینگ جدید' }}
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">اقامتگاه</label>
                        <select wire:model="formAccommodationId" class="form-select form-select-sm @error('formAccommodationId') is-invalid @enderror">
                            <option value="">— انتخاب اقامتگاه —</option>
                            @foreach($accommodations as $accommodation)
                            <option value="{{ $accommodation->id }}">{{ $accommodation->name }}@if($accommodation->city) — {{ $accommodation->city->name }}@endif</option>
                            @endforeach
                        </select>
                        @error('formAccommodationId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">آی‌پی ویندوز (Tailscale)</label>
                        <input wire:model="formWindowsLanIp" type="text" dir="ltr" class="form-control form-control-sm @error('formWindowsLanIp') is-invalid @enderror" placeholder="100.x.x.x">
                        @error('formWindowsLanIp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">آی‌پی پوز (شبکه مودم)</label>
                        <input wire:model="formPosLanIp" type="text" dir="ltr" class="form-control form-control-sm @error('formPosLanIp') is-invalid @enderror" placeholder="192.168.x.x">
                        @error('formPosLanIp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted">پورت پوز</label>
                        <input wire:model="formPosPort" type="number" dir="ltr" class="form-control form-control-sm @error('formPosPort') is-invalid @enderror">
                        @error('formPosPort')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted">پورت Agent</label>
                        <input wire:model="formAgentPort" type="number" dir="ltr" class="form-control form-control-sm @error('formAgentPort') is-invalid @enderror">
                        @error('formAgentPort')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted">یادداشت</label>
                        <input wire:model="formNotes" type="text" class="form-control form-control-sm" placeholder="مثلاً سیستم پذیرش اصفهان">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input wire:model="formIsActive" class="form-check-input" type="checkbox" id="pos-map-active">
                            <label class="form-check-label small" for="pos-map-active">فعال</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>{{ $editingId ? 'ذخیره تغییرات' : 'ثبت مپینگ' }}
                    </button>
                    @if($editingId)
                    <button type="button" wire:click="cancelEdit" class="btn btn-outline-secondary btn-sm">انصراف</button>
                    @endif
                </div>
                <p class="small text-muted mb-0 mt-2">
                    آی‌پی ویندوز همان آدرسی است که سیستم پذیرش روی Tailscale گرفته. آی‌پی پوز آدرس دستگاه روی شبکه داخلی مودم است.
                    سرور سامانه به آی‌پی ویندوز درخواست می‌زند و آی‌پی پوز را در بدنه می‌فرستد.
                </p>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form wire:submit="applyFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1 text-muted">جستجو</label>
                        <input type="search" wire:model="draftSearch" class="form-control form-control-sm" placeholder="نام اقامتگاه یا آی‌پی">
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
        <div class="card-header py-2">
            <span class="fw-semibold small">فهرست مپینگ‌ها</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>اقامتگاه</th>
                        <th>آی‌پی ویندوز</th>
                        <th>آی‌پی پوز</th>
                        <th>پورت‌ها</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mappings as $mapping)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $mapping->accommodation?->name }}</div>
                            <div class="small text-muted">#{{ $mapping->accommodation_id }}@if($mapping->accommodation?->city) · {{ $mapping->accommodation->city->name }}@endif</div>
                        </td>
                        <td><code dir="ltr">{{ $mapping->windows_lan_ip }}</code></td>
                        <td><code dir="ltr">{{ $mapping->pos_lan_ip }}</code></td>
                        <td class="small" dir="ltr">POS {{ $mapping->posPort() }} · Agent {{ $mapping->agentPort() }}</td>
                        <td>
                            @if($mapping->is_active)
                            <span class="badge bg-success-subtle text-success">فعال</span>
                            @else
                            <span class="badge bg-secondary-subtle text-secondary">غیرفعال</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" wire:click="edit({{ $mapping->id }})" class="btn btn-sm btn-outline-primary">ویرایش</button>
                            <button type="button"
                                    wire:click="delete({{ $mapping->id }})"
                                    data-swal-confirm="این مپینگ حذف شود؟"
                                    data-swal-confirm-variant="delete"
                                    class="btn btn-sm btn-outline-danger">حذف</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">هنوز مپینگی ثبت نشده است.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($mappings->hasPages())
        <div class="card-footer">{{ $mappings->links() }}</div>
        @endif
    </div>
</div>
