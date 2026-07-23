{{-- Country and residence city selectors with manual add support (included from Livewire view) --}}
<div class="col-md-6">
    <label class="form-label small fw-semibold">کشور اقامت</label>
    <select wire:model.live="foreignCountryId" class="form-select @error('foreignCountryId') is-invalid @enderror">
        <option value="0">انتخاب کشور</option>
        @foreach($countries as $country)
            <option value="{{ $country->id }}">{{ $country->name }}</option>
        @endforeach
    </select>
    @error('foreignCountryId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="mt-1">
        @if($showAddCountry)
            <div class="input-group input-group-sm">
                <input wire:model="newCountryName" type="text" class="form-control" placeholder="نام کشور جدید">
                <button wire:click="addCountry" type="button" class="btn btn-success">افزودن</button>
                <button wire:click="toggleAddCountry" type="button" class="btn btn-outline-secondary">انصراف</button>
            </div>
            @error('newCountryName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @else
            <button wire:click="toggleAddCountry" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="bi bi-plus-circle me-1"></i>کشور در لیست نیست؟ افزودن
            </button>
        @endif
    </div>
</div>

<div class="col-md-6">
    <label class="form-label small fw-semibold">شهر اقامت</label>
    <select wire:model.live="foreignResidenceCityId" class="form-select @error('foreignResidenceCityId') is-invalid @enderror" wire:key="foreign-city-{{ $foreignCountryId }}" @disabled(!$foreignCountryId)>
        <option value="0">{{ $foreignCountryId ? 'انتخاب شهر' : 'ابتدا کشور انتخاب کنید' }}</option>
        @foreach($residenceCities as $city)
            <option value="{{ $city->id }}">{{ $city->name }}</option>
        @endforeach
    </select>
    @error('foreignResidenceCityId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($foreignCountryId)
        <div class="mt-1">
            @if($showAddResidenceCity)
                <div class="input-group input-group-sm">
                    <input wire:model="newResidenceCityName" type="text" class="form-control" placeholder="نام شهر جدید">
                    <button wire:click="addResidenceCity" type="button" class="btn btn-success">افزودن</button>
                    <button wire:click="toggleAddResidenceCity" type="button" class="btn btn-outline-secondary">انصراف</button>
                </div>
                @error('newResidenceCityName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @else
                <button wire:click="toggleAddResidenceCity" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>شهر در لیست نیست؟ افزودن
                </button>
            @endif
        </div>
    @endif
</div>
