@once
@push('styles')
<style>
.rt-form-section {
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: 1.25rem;
    background: var(--bs-body-bg);
}
.rt-form-section--highlight {
    border-color: rgba(var(--bs-warning-rgb), .35);
    background: rgba(var(--bs-warning-rgb), .06);
}
.rt-form-section__title {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}
.rt-amenity-tile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    border: 1.5px solid var(--bs-border-color);
    border-radius: .5rem;
    padding: .65rem .85rem;
    cursor: pointer;
    user-select: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
    height: 100%;
    margin: 0;
    background: var(--bs-body-bg);
}
.rt-amenity-tile:hover {
    border-color: rgba(var(--bs-success-rgb), .45);
    background: rgba(var(--bs-success-rgb), .04);
}
.rt-amenity-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.rt-amenity-tile__label {
    font-size: .9rem;
    line-height: 1.4;
    flex: 1;
}
.rt-amenity-tile__check {
    flex-shrink: 0;
    font-size: 1rem;
    color: var(--bs-success);
    opacity: 0;
    transform: scale(.85);
    transition: opacity .15s, transform .15s;
}
.rt-amenity-tile:has(.rt-amenity-input:checked) {
    border-color: var(--bs-success);
    background: rgba(var(--bs-success-rgb), .1);
    box-shadow: inset 0 0 0 1px rgba(var(--bs-success-rgb), .15);
}
.rt-amenity-tile:has(.rt-amenity-input:checked) .rt-amenity-tile__check {
    opacity: 1;
    transform: scale(1);
}
.rt-amenity-tile:has(.rt-amenity-input:checked) .rt-amenity-tile__label {
    font-weight: 600;
    color: var(--bs-success-text-emphasis, var(--bs-success));
}
.col-6.col-md-4.col-lg-3[data-amenity-id] {
    position: relative;
}
.rt-amenity-remove {
    position: absolute;
    top: .35rem;
    inset-inline-start: .35rem;
    width: 1.35rem;
    height: 1.35rem;
    border: none;
    border-radius: 999px;
    background: rgba(var(--bs-danger-rgb), .12);
    color: var(--bs-danger);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: .65rem;
    line-height: 1;
    cursor: pointer;
    z-index: 2;
    opacity: 0;
    transition: opacity .15s, background .15s;
}
.col-6.col-md-4.col-lg-3[data-amenity-id]:hover .rt-amenity-remove,
.col-6.col-md-4.col-lg-3[data-amenity-id]:hover .rt-amenity-apply-all,
.rt-amenity-remove:focus-visible,
.rt-amenity-apply-all:focus-visible {
    opacity: 1;
}
.rt-amenity-remove:hover {
    background: rgba(var(--bs-danger-rgb), .22);
}
.rt-amenity-apply-all {
    position: absolute;
    top: .35rem;
    inset-inline-end: .35rem;
    width: 1.35rem;
    height: 1.35rem;
    border: none;
    border-radius: 999px;
    background: rgba(var(--bs-primary-rgb), .12);
    color: var(--bs-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: .7rem;
    line-height: 1;
    cursor: pointer;
    z-index: 2;
    opacity: 0;
    transition: opacity .15s, background .15s;
}
.rt-amenity-apply-all:hover {
    background: rgba(var(--bs-primary-rgb), .22);
}
.rt-catalog-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.rt-catalog-pill {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    font-size: .72rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 999px;
    padding: .15rem .45rem .15rem .35rem;
    background: var(--bs-body-bg);
}
.rt-catalog-pill__label {
    line-height: 1.3;
}
.rt-catalog-pill__rename {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.1rem;
    height: 1.1rem;
    border: none;
    border-radius: 999px;
    background: rgba(var(--bs-primary-rgb), .12);
    color: var(--bs-primary);
    padding: 0;
    font-size: .6rem;
    line-height: 1;
    cursor: pointer;
}
.rt-catalog-pill__rename:hover {
    background: rgba(var(--bs-primary-rgb), .22);
}
.rt-catalog-pill__rename-input {
    width: 7rem;
    min-width: 5rem;
    font-size: .72rem;
    padding: .1rem .35rem;
}
.rt-catalog-pill--renaming {
    gap: .25rem;
    padding-inline: .35rem;
}
.rt-catalog-pill__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.1rem;
    height: 1.1rem;
    border: none;
    border-radius: 999px;
    background: rgba(var(--bs-danger-rgb), .12);
    color: var(--bs-danger);
    padding: 0;
    font-size: .6rem;
    line-height: 1;
    cursor: pointer;
}
.rt-catalog-pill__remove:hover {
    background: rgba(var(--bs-danger-rgb), .22);
}
.rt-image-keep-label {
    position: relative;
    cursor: pointer;
    display: inline-block;
}
.rt-image-keep-label input[type="checkbox"] {
    position: absolute;
    top: .35rem;
    inset-inline-start: .35rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
    z-index: 1;
}
.rt-image-keep-label img {
    width: 100px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--bs-border-color);
}
.rt-image-keep-label:has(input:not(:checked)) img {
    opacity: .45;
    filter: grayscale(.4);
}
.rt-image-preview-thumb {
    width: 100px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--bs-border-color);
}
</style>
@endpush
@endonce
