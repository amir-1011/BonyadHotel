<div>
{{-- Reusable review form partial --}}
{{-- Variables: $bookingId, $currentRating (int), $currentComment (string) --}}
<div class="mb-3">
    <label class="form-label fw-semibold small">امتیاز شما</label>
    <div class="d-flex gap-2 fs-3" id="stars-show-{{ $bookingId }}">
        @for($r = 1; $r <= 5; $r++)
        <i class="bi bi-star{{ $r <= ($currentRating ?? 5) ? '-fill' : '' }} rating-star-show-{{ $bookingId }} text-warning"
           role="button"
           data-val="{{ $r }}"
           data-group="show-{{ $bookingId }}"
           style="cursor:pointer"></i>
        @endfor
    </div>
    <input type="hidden" name="rating" id="rating-show-{{ $bookingId }}" value="{{ $currentRating ?? 5 }}">
</div>
<div class="mb-3">
    <label class="form-label fw-semibold small">نظر شما (اختیاری)</label>
    <textarea name="comment" class="form-control" rows="4"
        placeholder="تجربه اقامت خود را با دیگران به اشتراک بگذارید...">{{ $currentComment ?? '' }}</textarea>
</div>
<button type="submit" class="btn btn-warning px-4">
    <i class="bi bi-send me-1"></i>ارسال نظر
</button>

</div>

@push('scripts')
<script>
(function(){
    const group = 'show-{{ $bookingId }}';
    function updateStarsShow(val) {
        document.querySelectorAll('.rating-star-show-{{ $bookingId }}').forEach((s, i) => {
            s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star-show-{{ $bookingId }} text-warning';
        });
    }
    document.querySelectorAll('.rating-star-show-{{ $bookingId }}').forEach(star => {
        star.addEventListener('click', function () {
            const val = parseInt(this.dataset.val);
            document.getElementById('rating-show-{{ $bookingId }}').value = val;
            updateStarsShow(val);
        });
        star.addEventListener('mouseenter', function () {
            updateStarsShow(parseInt(this.dataset.val));
        });
    });
    const container = document.getElementById('stars-show-{{ $bookingId }}');
    if (container) {
        container.addEventListener('mouseleave', function () {
            const val = parseInt(document.getElementById('rating-show-{{ $bookingId }}').value) || 5;
            updateStarsShow(val);
        });
    }
    updateStarsShow({{ $currentRating ?? 5 }});
})();
</script>
@endpush
