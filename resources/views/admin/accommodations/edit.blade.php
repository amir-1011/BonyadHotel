@extends('layouts.admin')
@section('title', 'ویرایش اقامتگاه')
@section('page-title', 'ویرایش اقامتگاه')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">ویرایش: {{ $accommodation->name }}</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.accommodations.update', $accommodation) }}">
            @csrf @method('PUT')
            @include('admin.accommodations._form', ['editing'=>true])
            <div class="mt-3">
                <button type="submit" class="btn btn-primary px-4">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('province_id')?.addEventListener('change', function(){
    fetch(`/api/provinces/${this.value}/cities`).then(r=>r.json()).then(cities=>{
        var sel = document.getElementById('city_id');
        sel.innerHTML = cities.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    });
});
</script>
@endpush
