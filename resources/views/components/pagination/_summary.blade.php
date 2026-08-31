<p class="small text-muted mb-0">
    نمایش
    <span class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits((string) $paginator->firstItem()) }}</span>
    تا
    <span class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits((string) $paginator->lastItem()) }}</span>
    از
    <span class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits((string) $paginator->total()) }}</span>
    نتیجه
</p>
