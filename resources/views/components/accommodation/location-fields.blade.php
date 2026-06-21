{{-- Province and city selectors with manual add support --}}
@props(['provinces', 'cities'])

<div class="col-md-6">
    <label class="form-label small fw-semibold">استان</label>
    <select wire:model.live="provinceId" class="form-select">
        <option value="0">انتخاب استان</option>
        @foreach($provinces as $prov)
            <option value="{{ $prov->id }}">{{ $prov->name }}</option>
        @endforeach
    </select>
    <div class="mt-1">
        @if($showAddProvince)
            <div class="input-group input-group-sm">
                <input wire:model="newProvinceName" type="text" class="form-control" placeholder="نام استان جدید">
                <button wire:click="addProvince" type="button" class="btn btn-success">افزودن</button>
                <button wire:click="toggleAddProvince" type="button" class="btn btn-outline-secondary">انصراف</button>
            </div>
            @error('newProvinceName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @else
            <button wire:click="toggleAddProvince" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="bi bi-plus-circle me-1"></i>استان در لیست نیست؟ افزودن
            </button>
        @endif
    </div>
</div>

<div class="col-md-6">
    <label class="form-label small fw-semibold">شهر</label>
    <select wire:model="cityId" class="form-select @error('cityId') is-invalid @enderror" @disabled(!$provinceId)>
        <option value="0">{{ $provinceId ? 'انتخاب شهر' : 'ابتدا استان انتخاب کنید' }}</option>
        @foreach($cities as $city)
            <option value="{{ $city->id }}">{{ $city->name }}</option>
        @endforeach
    </select>
    @error('cityId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($provinceId)
        <div class="mt-1">
            @if($showAddCity)
                <div class="input-group input-group-sm">
                    <input wire:model="newCityName" type="text" class="form-control" placeholder="نام شهر جدید">
                    <button wire:click="addCity" type="button" class="btn btn-success">افزودن</button>
                    <button wire:click="toggleAddCity" type="button" class="btn btn-outline-secondary">انصراف</button>
                </div>
                @error('newCityName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @else
                <button wire:click="toggleAddCity" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>شهر در لیست نیست؟ افزودن
                </button>
            @endif
        </div>
    @endif
</div>
