{{-- Shared booking management: per-guest services + form upload --}}
@php
    $guestRows = $booking->guestDetails->sortBy('sort_order');
    $unassignedServices = $booking->unassignedGuestServices();
    $isHostPanel = ($panel ?? 'guest') === 'host';
    $hostUser = auth()->user();
    $canEditServicesPanel = $booking->canEditServices()
        && (!$isHostPanel || $hostUser?->hostCanAny('bookings.services', ['write', 'edit']));
    $canManageForms = !$isHostPanel || $hostUser?->hostCanAny('bookings.forms', ['write', 'delete']);
    $canViewPdf = !$isHostPanel || $hostUser?->hostCan('bookings.pdf', 'read');
    $veteranApplied = !empty($booking->veteran_type_applied);
@endphp

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-bag-check me-2"></i>مدیریت خدمات و فرم رزرو</span>
        @if($canViewPdf)
        <a href="{{ route($panel . '.bookings.pdf', $booking) }}" target="_blank" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-pdf"></i>دانلود PDF
        </a>
        @endif
    </div>
    <div class="card-body">
        @if($guestRows->isNotEmpty())
        <div class="small text-muted mb-3">
            <i class="bi bi-people me-1"></i>
            خدمات به‌ازای هر مهمان مدیریت می‌شود. برای هر مهمان می‌توانید سهمیه ایثارگری، تخفیف دستی و قیمت/تعداد را جداگانه تنظیم کنید.
        </div>

        @foreach($guestRows as $guest)
        <x-booking.guest-services-panel
            :booking="$booking"
            :guest="$guest"
            :panel="$panel"
            :editable="$canEditServicesPanel" />
        @endforeach
        @elseif($canEditServicesPanel)
        <livewire:booking-services-editor
            :booking-id="$booking->id"
            :panel="$panel"
            :key="'booking-show-services-all-'.$booking->id" />
        @elseif($booking->services->isNotEmpty())
        <div class="d-flex flex-column gap-2 mb-3">
            @foreach($booking->services as $service)
            <x-booking.service-line-readonly
                :service="$service"
                :veteran-type-applied="$veteranApplied" />
            @endforeach
        </div>
        @else
        <div class="alert alert-light border small py-2 mb-3">هنوز خدمتی برای این رزرو ثبت نشده است.</div>
        @endif

        @if($unassignedServices->isNotEmpty())
        <div class="border rounded p-3 bg-white mb-3">
            <div class="small fw-semibold mb-2 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>خدمات بدون مهمان مشخص
            </div>
            <div class="d-flex flex-column gap-2">
                @foreach($unassignedServices as $service)
                <x-booking.service-line-readonly
                    :service="$service"
                    :veteran-type-applied="$veteranApplied" />
                @endforeach
            </div>
        </div>
        @endif

        @if(!$canEditServicesPanel && $booking->services->isEmpty())
        <div class="alert alert-light border small py-2 mb-0">خدمت اضافی ثبت نشده است.</div>
        @endif

        @if($booking->isMedicalAccommodation() && $booking->hasMedicalReferralLetters())
        <div class="border rounded p-3 mt-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-hospital me-1"></i>معرفی‌نامه اسکان درمانی</div>
            <x-booking.authorized-document-links
                :urls="collect($booking->medicalReferralLetterPaths())->map(fn ($path, $index) => $booking->medicalReferralLetterUrl($panel ?? null, $index))->all()"
                btn-class="btn-outline-info"
                label="دانلود معرفی‌نامه"
            />
        </div>
        @endif

        @if($booking->isCredit() && $booking->hasCreditLetters())
        <div class="border rounded p-3 mt-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-wallet2 me-1"></i>معرفی‌نامه اعتباری</div>
            <x-booking.authorized-document-links
                :urls="collect($booking->creditLetterPaths())->map(fn ($path, $index) => $booking->creditLetterUrl($panel ?? null, $index))->all()"
                btn-class="btn-outline-warning"
                label="دانلود معرفی‌نامه"
            />
        </div>
        @endif

        @if($canManageForms && $booking->canEditServices())
        <div class="border rounded p-3 mt-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-upload me-1"></i>فرم رزرو امضا‌شده</div>
            @if($booking->form_file_path)
            <div class="d-flex align-items-center gap-2 mb-2">
                <a href="{{ asset('storage/' . $booking->form_file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>مشاهده فایل</a>
                <x-host.can page="bookings.forms" action="delete" :panel="$panel">
                <button type="button" wire:click="deleteBookingForm" class="btn btn-sm btn-outline-danger" data-swal-confirm="فایل حذف شود؟">حذف</button>
                </x-host.can>
            </div>
            @endif
            <x-host.can page="bookings.forms" action="write" :panel="$panel">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <input type="file" wire:model="uploadedForm" class="form-control form-control-sm" style="max-width:280px" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">PDF یا تصویر — حداکثر ۲۰ مگابایت</div>
                <button type="button" wire:click="uploadBookingForm" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="uploadedForm,uploadBookingForm">آپلود</button>
            </div>
            @error('uploadedForm')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </x-host.can>
        </div>
        @elseif($booking->form_file_path)
        <div class="border rounded p-3 mt-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-upload me-1"></i>فرم رزرو امضا‌شده</div>
            <a href="{{ asset('storage/' . $booking->form_file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>مشاهده فایل</a>
        </div>
        @endif
    </div>
</div>
