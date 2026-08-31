<div>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'categories')"
                    class="nav-link {{ $tab === 'categories' ? 'active' : '' }}">دسته‌بندی‌ها</button>
        </li>
        <li class="nav-item">
            <button type="button" wire:click="$set('tab', 'brands')"
                    class="nav-link {{ $tab === 'brands' ? 'active' : '' }}">برندها</button>
        </li>
    </ul>

    @if($tab === 'categories')
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    موارد اضافه‌شده هنگام ثبت فرم در اینجا قابل ویرایش و حذف هستند. اگر دسته‌بندی در موارد ثبت‌شده استفاده شده باشد، حذف آن امکان‌پذیر نیست.
                </div>
                <form wire:submit.prevent="saveCategories">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>نام دسته‌بندی</th>
                                    <th class="text-center">تعداد موارد</th>
                                    <th class="text-end" style="width:100px">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    <tr wire:key="facility-category-{{ $category->id }}">
                                        <td>
                                            <input
                                                type="text"
                                                wire:model="categoryNames.{{ $category->id }}"
                                                class="form-control form-control-sm @error('categoryNames.'.$category->id) is-invalid @enderror"
                                            >
                                            @error('categoryNames.'.$category->id)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center">{{ $category->items_count }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                wire:click="deleteCategory({{ $category->id }})"
                                                data-swal-confirm="این دسته‌بندی حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($category->items_count > 0)
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">دسته‌بندی ثبت نشده است.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($categories->isNotEmpty())
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <i class="bi bi-save me-1"></i>ذخیره تغییرات
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif

    @if($tab === 'brands')
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    برندهای اضافه‌شده هنگام ثبت فرم در اینجا قابل ویرایش و حذف هستند.
                </div>
                <form wire:submit.prevent="saveBrands">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>نام برند</th>
                                    <th class="text-center">تعداد موارد</th>
                                    <th class="text-end" style="width:100px">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($brands as $brand)
                                    <tr wire:key="facility-brand-{{ $brand->id }}">
                                        <td>
                                            <input
                                                type="text"
                                                wire:model="brandNames.{{ $brand->id }}"
                                                class="form-control form-control-sm @error('brandNames.'.$brand->id) is-invalid @enderror"
                                            >
                                            @error('brandNames.'.$brand->id)
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center">{{ $brand->items_count }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                wire:click="deleteBrand({{ $brand->id }})"
                                                data-swal-confirm="این برند حذف شود؟"
                                                class="btn btn-sm btn-outline-danger"
                                                @disabled($brand->items_count > 0)
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">برندی ثبت نشده است.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($brands->isNotEmpty())
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <i class="bi bi-save me-1"></i>ذخیره تغییرات
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif
</div>
