<div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small d-flex align-items-center justify-content-between">
                <span><i class="bi bi-person-badge me-1"></i>سمت‌های کاربر</span>
                <button type="button" wire:click="toggleAddPosition" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body p-0">
                @if($showAddPosition)
                    <div class="p-3 border-bottom bg-light">
                        <label class="form-label small text-muted mb-1">سمت جدید</label>
                        <div class="input-group input-group-sm">
                            <input wire:model="newPositionLabel" type="text" class="form-control" placeholder="نام سمت">
                            <button wire:click="addPosition" type="button" class="btn btn-success">افزودن</button>
                        </div>
                        @error('newPositionLabel')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <button wire:click="toggleAddPosition" type="button" class="btn btn-link btn-sm p-0 mt-1 text-decoration-none">انصراف</button>
                    </div>
                @else
                    <div class="px-3 pt-3 pb-2">
                        <button wire:click="toggleAddPosition" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                            <i class="bi bi-plus-circle me-1"></i>سمت در لیست نیست؟ افزودن
                        </button>
                    </div>
                @endif

                @if($positions->isEmpty())
                    <p class="text-muted small px-3 pb-3 mb-0">هنوز سمتی تعریف نشده است. با دکمه بالا اولین سمت را اضافه کنید.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($positions as $position)
                            <button
                                type="button"
                                wire:click="selectPosition({{ $position->id }})"
                                wire:key="position-{{ $position->id }}"
                                class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2 {{ $selectedPositionId === $position->id ? 'active' : '' }}"
                            >
                                <span class="small fw-semibold">{{ \App\Support\HostLabels::displayPositionLabel($position->label) }}</span>
                                @if($position->label === \App\Support\HostPositionTitles::DEFAULT_LABEL)
                                    <span class="badge {{ $selectedPositionId === $position->id ? 'bg-light text-dark' : 'bg-primary-subtle text-primary border' }}">پیش‌فرض</span>
                                @elseif($position->host_panel_permissions)
                                    <span class="badge {{ $selectedPositionId === $position->id ? 'bg-light text-dark' : 'bg-success-subtle text-success border' }}">تعریف‌شده</span>
                                @else
                                    <span class="badge {{ $selectedPositionId === $position->id ? 'bg-light text-dark' : 'bg-secondary-subtle text-muted border' }}">پیش‌فرض</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body small text-muted">
                <p class="mb-2">در این بخش برای هر <strong>سمت</strong>، ماتریس دسترسی پنل کاربر تعریف می‌شود.</p>
                <p class="mb-2">هنگام ایجاد یا ویرایش کاربر، با انتخاب سمت، همین دسترسی‌ها به‌صورت خودکار اعمال می‌شوند.</p>
                <p class="mb-0">سمت «کاربر» پیش‌فرض کاربران جدید است. سمت‌های بدون الگوی ذخیره‌شده، دسترسی کامل دریافت می‌کنند.</p>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-sliders me-1"></i>
                @if($selectedPosition)
                    دسترسی‌های پنل — {{ \App\Support\HostLabels::displayPositionLabel($selectedPosition->label) }}
                @else
                    دسترسی‌های پنل کاربر
                @endif
            </div>
            <div class="card-body">
                @if($selectedPosition)
                    <div class="mb-4">
                        <label class="form-label small text-muted">نام سمت</label>
                        <div class="input-group input-group-sm">
                            <input
                                wire:model="editingPositionLabel"
                                type="text"
                                class="form-control @error('editingPositionLabel') is-invalid @enderror"
                                placeholder="نام سمت"
                            >
                            <button wire:click="updatePositionLabel" type="button" class="btn btn-outline-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="updatePositionLabel"><i class="bi bi-pencil me-1"></i>ذخیره نام</span>
                                <span wire:loading wire:target="updatePositionLabel">...</span>
                            </button>
                        </div>
                        @error('editingPositionLabel')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <div class="form-text">با تغییر نام، سمت کاربران مرتبط هم به‌روز می‌شود.</div>
                    </div>

                    <x-admin.host-permissions-matrix
                        :catalog="$hostPermissionCatalog"
                        :form-state="$hostPermissionForm"
                    />
                    <div class="d-flex justify-content-end mt-3">
                        <button wire:click="save" type="button" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save"><i class="bi bi-check2-circle me-1"></i>ذخیره دسترسی‌های این سمت</span>
                            <span wire:loading wire:target="save">در حال ذخیره...</span>
                        </button>
                    </div>
                @else
                    <p class="text-muted mb-0">از فهرست سمت‌ها یک مورد انتخاب کنید یا سمت جدید اضافه کنید.</p>
                @endif
            </div>
        </div>
    </div>
</div>

</div>
