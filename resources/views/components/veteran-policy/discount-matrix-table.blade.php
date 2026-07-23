{{-- جدول ماتریس تخفیف — اسکرول با سرستون و عنوان ردیف ثابت (مانند اکسل) --}}
@props([
    'groups' => [],
    'services' => [],
    'discountMatrix' => [],
    'serviceRefField' => 'id',
    'showColumnHelp' => false,
])

<div class="vp-matrix-scroll" role="region" aria-label="جدول تخفیف خدمات — اسکرول افقی و عمودی">
    <table class="table table-sm align-middle mb-0 vp-matrix-table" style="font-size:.85rem">
        <thead class="table-light">
            <tr>
                <th class="vp-matrix-sticky-col">
                    @if($showColumnHelp)
                    <span class="d-inline-flex align-items-center gap-1">
                        گروه \ خدمت
                        <x-admin.column-help title="ماتریس تخفیف">
                            هر سلول درصد تخفیف یک خدمت را برای یک گروه ایثارگری مشخص می‌کند.
                            <ul class="mt-2">
                                <li>ردیف = گروه مشمول (جانباز، شهید، …)</li>
                                <li>ستون = نوع خدمت (استخر، سالن، …)</li>
                                <li>حالت عادی: درصد تخفیف همیشگی — حالت پله‌ای: جزئیات هفتگی</li>
                            </ul>
                        </x-admin.column-help>
                    </span>
                    @else
                    گروه \ خدمت
                    @endif
                </th>
                @foreach($services as $service)
                <th class="text-center" style="min-width:100px">
                    @if($showColumnHelp)
                    <span class="d-inline-flex align-items-center justify-content-center gap-1 flex-wrap">
                        {{ $service['name'] }}
                        <x-admin.column-help :title="$service['name']">
                            @if($service['supports_free_sessions'] ?? false)
                                خدمت با سقف هفتگی — در حالت عادی درصد همیشگی؛ با تیک «پله‌ای» جلسات رایگان و مبلغ ثابت تعریف کنید.
                            @else
                                درصد تخفیف همیشگی این خدمت برای هر گروه (۰ تا ۱۰۰).
                            @endif
                        </x-admin.column-help>
                    </span>
                    @else
                    {{ $service['name'] }}
                    @endif
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
            <tr wire:key="mx-{{ $group['key'] }}">
                <td class="vp-matrix-sticky-col small">{{ $group['label'] }}</td>
                @foreach($services as $service)
                @php
                    $serviceRef = $service[$serviceRefField] ?? $service['id'];
                    $cell = $discountMatrix[$group['key']][$serviceRef] ?? [];
                @endphp
                <td wire:key="mx-{{ $group['key'] }}-{{ $serviceRef }}">
                    <x-veteran-policy.discount-matrix-cell
                        :group-key="$group['key']"
                        :service-ref="$serviceRef"
                        :cell="$cell"
                    />
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
