<div class="card shadow-sm mb-3">
    <div class="ta-list-chrome">
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0">
            <input
                type="text"
                wire:model="filterSearch"
                class="form-control form-control-sm"
                style="max-width:16rem"
                placeholder="جستجو نام، برند، توضیحات…"
            >
            <select wire:model="filterCategoryId" class="form-select form-select-sm" style="max-width:10rem">
                <option value="0">همه دسته‌بندی‌ها</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model="filterProvinceId" class="form-select form-select-sm" style="max-width:10rem">
                <option value="0">همه استان‌ها</option>
                @foreach($provinces as $province)
                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                @endforeach
            </select>
            @if($showMineOnlyFilter)
                <select wire:model="filterMineOnly" class="form-select form-select-sm" style="max-width:10rem">
                    <option value="">{{ $type === 'surplus' ? 'همه موارد' : 'همه درخواست‌ها' }}</option>
                    <option value="1">{{ $type === 'surplus' ? 'فقط موارد من' : 'فقط درخواست‌های من' }}</option>
                </select>
            @endif
            <button type="button" wire:click="applyFilters" class="btn btn-sm btn-primary">
                اعمال
            </button>
        </div>
        @if(!empty($showCreateButton))
        <div class="ta-page-toolbar">
            @if(($panel ?? 'host') === 'admin')
                <a wire:navigate href="{{ $createRoute }}" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-circle me-1"></i>{{ $createButtonLabel }}
                </a>
            @else
                <x-host.can :page="$createPermissionPage" action="write">
                    <a wire:navigate href="{{ $createRoute }}" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i>{{ $createButtonLabel }}
                    </a>
                </x-host.can>
            @endif
        </div>
        @endif
    </div>
</div>
