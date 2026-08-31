<div>

@include('components.facility._filters', [
    'categories' => $categories,
    'provinces' => $provinces,
    'type' => $type,
    'showMineOnlyFilter' => $showMineOnlyFilter,
    'showCreateButton' => $showCreateButton,
    'createRoute' => $createRoute ?? null,
    'createButtonLabel' => $createButtonLabel ?? null,
    'createPermissionPage' => $createPermissionPage ?? null,
    'panel' => $panel ?? 'host',
])

@if($items->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            {{ $emptyText }}
        </div>
    </div>
@else
    <div class="facility-listing-grid">
        @foreach($items as $item)
            @include('components.facility._item-card', [
                'item' => $item,
                'type' => $type,
                'panel' => $panel,
            ])
        @endforeach
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
@endif

@if($detailItem)
    @include('components.facility._item-detail-modal', [
        'detailItem' => $detailItem,
        'type' => $type,
        'panel' => $panel,
    ])
@endIf

@once
    @include('components.facility._listing-styles')
@endonce

</div>
