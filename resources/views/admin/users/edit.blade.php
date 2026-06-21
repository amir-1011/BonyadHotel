<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">ویرایش اطلاعات {{ $user->name ?? $user->mobile }}</h5>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success py-1 small">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نام</label>
                        <input type="text" wire:model="name" class="form-control" placeholder="نام کاربر">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">موبایل</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->mobile }}" disabled>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">کد ملی</label>
                        <input type="text" wire:model="nationalId" class="form-control" placeholder="کد ملی ۱۰ رقمی" dir="ltr">
                        @error('nationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">با تغییر کد ملی، گروه ایثارگری از سرویس استعلام به‌روز می‌شود (در صورت تأیید).</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نقش</label>
                        <select wire:model="role" class="form-select">
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-shield-check me-1"></i>گروه ایثارگری</label>
                        <select wire:model.live="veteranType" class="form-select">
                            @foreach($veteranGroups as $key => $group)
                            <option value="{{ $key }}">{{ $group['label'] }} @if($key && $group['discount'] > 0)({{ $group['discount'] }}٪ اقامت)@endif</option>
                            @endforeach
                        </select>
                        @error('veteranType')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">گروه‌های ایثارگری از <a href="{{ route('admin.veteran-policy') }}" wire:navigate>تنظیمات ایثارگری</a> خوانده می‌شوند.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">درصد تخفیف اقامت</label>
                        <div class="input-group">
                            <input type="number" wire:model="discountPct" min="0" max="100" class="form-control" @if(!$veteranType) disabled @endif>
                            <button type="button" wire:click="syncDiscountFromGroup" class="btn btn-outline-secondary" @if(!$veteranType) disabled @endif title="همگام با گروه">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                        @error('discountPct')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">معمولاً از گروه انتخاب‌شده پر می‌شود؛ در موارد خاص قابل تغییر دستی است.</div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <div class="fw-semibold mb-1">وضعیت فعلی</div>
                            <div class="small">موبایل: <span class="badge {{ $user->mobile_verified_at ? 'bg-success' : 'bg-danger' }}">{{ $user->mobile_verified_at ? 'تأیید شده' : 'تأیید نشده' }}</span></div>
                            <div class="small mt-1">کد ملی: <span class="badge {{ $user->national_id_verified_at ? 'bg-success' : 'bg-secondary' }}">{{ $user->national_id_verified_at ? 'تأیید شده' : 'تأیید نشده' }}</span></div>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">انصراف</a>
                        <button wire:click="update" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </div>
            </div>
        </div>

        @if($role === 'host')
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-shield-lock me-1"></i>رمز عبور پنل میزبان
            </div>
            <div class="card-body">
                @if(session('password_status'))
                    <div class="alert alert-success py-1 small">{{ session('password_status') }}</div>
                @endif
                <p class="text-muted small mb-3">ادمین می‌تواند بدون نیاز به رمز فعلی، رمز ورود میزبان به پنل را تغییر دهد.</p>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <x-password-input label="رمز عبور جدید" wire:model="hostPassword" placeholder="حداقل ۶ کاراکتر" />
                        @error('hostPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <x-password-input label="تکرار رمز عبور جدید" wire:model="hostPassword_confirmation" placeholder="رمز جدید را مجدداً وارد کنید" />
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" wire:click="updateHostPassword" class="btn btn-sm btn-outline-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="updateHostPassword"><i class="bi bi-key me-1"></i>ذخیره رمز جدید</span>
                            <span wire:loading wire:target="updateHostPassword">در حال ذخیره...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-sliders me-1"></i>دسترسی‌های پنل میزبان
            </div>
            <div class="card-body">
                @error('hostPanelPermissions')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                <p class="text-muted small mb-3">بخش‌هایی از پنل میزبان که این کاربر می‌تواند ببیند. داده‌ها بر اساس اقامتگاه‌های نسبت‌داده‌شده فیلتر می‌شوند.</p>
                <div class="row g-2">
                    @foreach($hostPermissionCatalog as $key => $item)
                    <div class="col-12 col-md-6">
                        <label class="d-flex align-items-start gap-2 border rounded p-2 h-100 mb-0" style="cursor:pointer">
                            <input type="checkbox" class="form-check-input mt-1" wire:model="hostPanelPermissions" value="{{ $key }}">
                            <span>
                                <span class="fw-semibold small d-block">
                                    <i class="bi bi-{{ $item['icon'] }} me-1 text-primary"></i>{{ $item['label'] }}
                                </span>
                                <span class="text-muted small">{{ $item['description'] }}</span>
                            </span>
                        </label>
                    </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button" wire:click="saveHostPanelAccess" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-check2-circle me-1"></i>ذخیره دسترسی‌های پنل
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-building me-1"></i>اقامتگاه‌های میزبان
            </div>
            <div class="card-body">
                @error('accommodations')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

                @if($assignedAccommodations->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>نام</th>
                                <th>شهر</th>
                                <th>میزبان‌های دیگر</th>
                                <th>وضعیت</th>
                                <th class="text-end">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignedAccommodations as $acc)
                            <tr wire:key="assigned-acc-{{ $acc->id }}">
                                <td class="small fw-semibold">{{ $acc->name }}</td>
                                <td class="small text-muted">{{ $acc->city->name ?? '—' }}</td>
                                <td class="small">
                                    @php $otherHosts = $acc->hosts->where('id', '!=', $user->id); @endphp
                                    @if($otherHosts->isNotEmpty())
                                        <span class="text-muted">{{ $otherHosts->map(fn($h) => $h->name ?? $h->mobile)->join('، ') }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }}">
                                        {{ $acc->is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        wire:click="revokeAccommodation({{ $acc->id }})"
                                        data-swal-confirm="دسترسی این میزبان به «{{ $acc->name }}» لغو شود؟"
                                        class="btn btn-sm btn-outline-danger"
                                    >
                                        <i class="bi bi-x-circle me-1"></i>لغو دسترسی
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted small mb-3">هنوز اقامتگاهی به این میزبان نسبت داده نشده است.</p>
                @endif

                @if($availableAccommodations->isNotEmpty())
                <div class="border-top pt-3">
                    <label class="form-label small text-muted fw-semibold">افزودن اقامتگاه</label>
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <select wire:model="accommodationToAssign" class="form-select form-select-sm">
                            <option value="">انتخاب اقامتگاه...</option>
                            @foreach($availableAccommodations as $acc)
                            <option value="{{ $acc->id }}">
                                {{ $acc->name }}
                                @if($acc->city) — {{ $acc->city->name }} @endif
                                @if($acc->hosts_count > 0)
                                    ({{ $acc->hosts_count }} میزبان دیگر)
                                @else
                                    (بدون میزبان)
                                @endif
                            </option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="assignAccommodation" class="btn btn-sm btn-success text-nowrap">
                            <i class="bi bi-plus-circle me-1"></i>نسبت دادن
                        </button>
                    </div>
                    @error('accommodationToAssign')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text">می‌توانید یک اقامتگاه را به چند میزبان نسبت دهید؛ میزبان‌های قبلی همچنان دسترسی خود را حفظ می‌کنند.</div>
                </div>
                @else
                <p class="text-muted small mb-0 border-top pt-3">همه اقامتگاه‌ها از قبل به این میزبان نسبت داده شده‌اند.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small">خلاصه کاربر</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">نقش‌ها</span><strong>{{ $user->roles->pluck('name')->join('، ') ?: 'guest' }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">گروه ایثارگری</span><strong>{{ $veteranType ? ($veteranGroups[$veteranType]['label'] ?? '—') : 'کاربر عادی' }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">تخفیف اقامت</span><strong>{{ $discountPct }}%</strong></div>
            </div>
        </div>
    </div>
</div>

</div>
