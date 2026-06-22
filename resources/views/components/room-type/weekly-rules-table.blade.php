@props([
    'weeklyRules',
    'destroyRouteName',
    'accommodation',
    'roomType',
])

<div class="card shadow-sm border-0 rounded-4 mt-3">
    <div class="card-header bg-white border-bottom rounded-top-4">
        <h6 class="mb-0 fw-bold"><i class="bi bi-arrow-repeat me-2"></i>قوانین دائمی هفتگی ({{ $weeklyRules->count() }})</h6>
    </div>
    <div class="card-body p-0">
        @if($weeklyRules->isEmpty())
        <div class="text-center py-4 text-muted small">
            <i class="bi bi-calendar-week d-block fs-4 mb-2"></i>
            هنوز قانون هفتگی ثبت نشده است.
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>روز هفته</th>
                        <th>تغییر قیمت</th>
                        <th>قیمت سفارشی</th>
                        <th>برچسب</th>
                        <th>دلیل</th>
                        <th class="text-end">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($weeklyRules as $rule)
                <tr>
                    <td class="fw-semibold">{{ $rule->weekdayLabel() }}</td>
                    <td>
                        @if($rule->discount_percentage)
                            @if($rule->discount_percentage > 0)
                                <span class="badge bg-success">{{ $rule->discount_percentage }}% تخفیف</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ $rule->discount_percentage }}% (گران‌تر)</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $rule->custom_price ? number_format($rule->custom_price, 0, '.', ',') . ' ت' : '—' }}</td>
                    <td class="text-muted small">{{ $rule->price_label ?: '—' }}</td>
                    <td class="text-muted small">{{ $rule->reason ?: '—' }}</td>
                    <td class="text-end">
                        <form action="{{ route($destroyRouteName, [$accommodation, $roomType, $rule]) }}"
                              method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" data-swal-confirm="این قانون هفتگی حذف شود؟"
                                    class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
