{{-- Shared hall form fields (admin + host) --}}
@props(['hall' => null])

@php
    use App\Support\CatalogPermissions;

    $typeCatalog = app(\App\Services\HallTypeCatalogService::class)->allOrdered();
    $amenityCatalog = app(\App\Services\HallAmenityCatalogService::class)->allOrdered();
    $oldAmenities = old('amenities', $hall?->amenities ?? []);
    $selectedTypeId = (int) old('hall_type_id', $hall?->hall_type_id);
    $authUser = auth()->user();
@endphp

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-4" data-hall-form
     data-amenity-store-url="{{ route('api.hall-amenities.store') }}"
     data-amenity-destroy-url="{{ url('/api/hall-amenities') }}"
     data-type-store-url="{{ route('api.hall-types.store') }}"
     data-type-update-url="{{ url('/api/hall-types') }}"
     data-type-destroy-url="{{ url('/api/hall-types') }}">

    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-info-circle text-primary"></i>
                <span>اطلاعات سالن</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">نام سالن <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $hall?->name) }}" placeholder="مثلاً: سالن اصلی">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">نوع سالن <span class="text-danger">*</span></label>
                    <select name="hall_type_id" data-hall-type-select class="form-select @error('hall_type_id') is-invalid @enderror">
                        <option value="">— انتخاب کنید —</option>
                        @foreach($typeCatalog as $type)
                        <option value="{{ $type->id }}" data-type-id="{{ $type->id }}" @selected($selectedTypeId === (int) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('hall_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div data-hall-types-catalog class="rt-catalog-pills mt-2">
                        @foreach($typeCatalog as $type)
                        <span class="rt-catalog-pill" data-type-id="{{ $type->id }}">
                            <span class="rt-catalog-pill__label">{{ $type->name }}</span>
                            @if(CatalogPermissions::canEdit($authUser, $type->created_by))
                            <button type="button"
                                    class="rt-catalog-pill__rename"
                                    data-action="rename-hall-type"
                                    data-type-id="{{ $type->id }}"
                                    data-type-name="{{ $type->name }}"
                                    title="تغییر نام"
                                    aria-label="تغییر نام {{ $type->name }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @endif
                            @if(CatalogPermissions::canDelete($authUser, $type->created_by))
                            <button type="button"
                                    class="rt-catalog-pill__remove"
                                    data-action="remove-hall-type"
                                    data-type-id="{{ $type->id }}"
                                    data-type-name="{{ $type->name }}"
                                    title="حذف از لیست سراسری"
                                    aria-label="حذف {{ $type->name }} از لیست">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            @endif
                        </span>
                        @endforeach
                    </div>
                    <div class="mt-1">
                        <div data-hall-type-add-panel class="d-none">
                            <div class="input-group input-group-sm">
                                <input data-hall-type-new-input type="text" class="form-control" placeholder="نام نوع سالن جدید">
                                <button data-action="confirm-add-hall-type" type="button" class="btn btn-success">افزودن</button>
                                <button data-action="cancel-add-hall-type" type="button" class="btn btn-outline-secondary">انصراف</button>
                            </div>
                            <div data-hall-type-error class="text-danger small mt-1 d-none"></div>
                        </div>
                        <button data-action="toggle-add-hall-type" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                            <i class="bi bi-plus-circle me-1"></i>نوع در لیست نیست؟ افزودن
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">ظرفیت (نفر) <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" class="form-control @error('capacity') is-invalid @enderror"
                           value="{{ old('capacity', $hall?->capacity ?? 50) }}" min="1" max="5000">
                    @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">ترتیب نمایش</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $hall?->sort_order ?? 0) }}" min="0">
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch mt-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="hallActiveCheck"
                               @checked(old('is_active', $hall?->is_active ?? true))>
                        <label class="form-check-label" for="hallActiveCheck">فعال</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">توضیحات</label>
                    <textarea name="description" rows="3" class="form-control">{{ old('description', $hall?->description) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-stars text-success"></i>
                <span>امکانات سالن</span>
            </div>
            <div data-amenities-grid class="row g-2">
                @foreach($amenityCatalog as $amenity)
                <div class="col-6 col-md-4 col-lg-3" data-amenity-id="{{ $amenity->id }}">
                    <label class="rt-amenity-tile">
                        <input type="checkbox" name="amenities[]" value="{{ $amenity->name }}"
                               class="rt-amenity-input" id="ham_{{ $amenity->id }}"
                               @checked(in_array($amenity->name, $oldAmenities, true))>
                        <span class="rt-amenity-tile__label">{{ $amenity->name }}</span>
                        <i class="bi bi-check-circle-fill rt-amenity-tile__check" aria-hidden="true"></i>
                    </label>
                    @if(CatalogPermissions::canDelete($authUser, $amenity->created_by))
                    <button type="button"
                            class="rt-amenity-remove"
                            data-action="remove-amenity"
                            data-amenity-id="{{ $amenity->id }}"
                            data-amenity-name="{{ $amenity->name }}"
                            title="حذف از لیست سراسری"
                            aria-label="حذف {{ $amenity->name }} از لیست">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="mt-2">
                <div data-amenity-add-panel class="d-none">
                    <div class="input-group input-group-sm" style="max-width: 420px;">
                        <input data-amenity-new-input type="text" class="form-control" placeholder="نام امکان جدید">
                        <button data-action="confirm-add-amenity" type="button" class="btn btn-success">افزودن</button>
                        <button data-action="cancel-add-amenity" type="button" class="btn btn-outline-secondary">انصراف</button>
                    </div>
                    <div data-amenity-error class="text-danger small mt-1 d-none"></div>
                </div>
                <button data-action="toggle-add-amenity" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>امکان در لیست نیست؟ افزودن
                </button>
            </div>
        </div>
    </div>
</div>

@include('partials._catalog-form-styles')
