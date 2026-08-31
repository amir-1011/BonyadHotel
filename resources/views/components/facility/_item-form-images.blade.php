    @if($showImage ?? false)
        <div class="col-12">
            @if(!empty($keptImagePaths))
                <label class="form-label small fw-semibold d-block">عکس‌های فعلی</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach($keptImagePaths as $path)
                        <div class="position-relative border rounded overflow-hidden" style="width:120px;height:96px;">
                            <img src="{{ asset('storage/' . $path) }}" alt="" class="w-100 h-100" style="object-fit:cover;">
                            <button
                                type="button"
                                wire:click="removeKeptImage('{{ $path }}')"
                                class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                title="حذف"
                            >
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div
                class="image-upload-panel"
                data-image-upload-panel
                data-upload-property="images"
                x-data="imageUploadGate('images')"
            >
                <label class="form-label small fw-semibold" for="facility-item-images">
                    <i class="bi bi-images me-1"></i>عکس‌های کالا
                    <span class="text-muted fw-normal">(اختیاری — {{ \App\Services\ImageUploadService::helpText() }})</span>
                </label>
                <input
                    type="file"
                    id="facility-item-images"
                    wire:model="images"
                    class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
                    accept="{{ \App\Services\ImageUploadService::acceptAttribute() }}"
                    data-max-bytes="{{ \App\Services\ImageUploadService::maxBytes() }}"
                    multiple
                >
                <div wire:loading wire:target="images" class="text-muted small mt-1">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    در حال ارسال تصاویر…
                </div>
                @error('images')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('images.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    @endif
