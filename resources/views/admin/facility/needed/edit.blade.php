<div>

<div class="card shadow-sm">
    <div class="card-body">
        @include('components.facility._item-form-fields', [
            'showImage' => false,
            'existingImageUrl' => null,
            'submitLabel' => 'ذخیره تغییرات',
            'submitAction' => 'update',
        ])
    </div>
</div>

</div>
