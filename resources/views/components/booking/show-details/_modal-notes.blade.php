@if($booking->notes)
<div class="mb-3">
    <div class="text-muted mb-1">یادداشت رزرو</div>
    <div class="border rounded p-2 bg-light">{{ $booking->notes }}</div>
</div>
@endif
@if($booking->form_file_path)
<div>
    <div class="text-muted mb-1">فرم رزرو امضا‌شده</div>
    <a href="{{ asset('storage/' . $booking->form_file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-file-earmark me-1"></i>مشاهده فایل
    </a>
</div>
@endif
@if(!$booking->notes && !$booking->form_file_path)
<p class="text-muted mb-0">یادداشت یا پیوستی ثبت نشده است.</p>
@endif
