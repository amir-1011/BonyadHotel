<div class="staff-auth-shell">
    <div style="width:100%;max-width:440px;">
        <div style="text-align:center;margin-bottom:28px;">
            <div style="display:inline-flex;align-items:center;gap:12px;">
                <img src="{{ vasset('logo/site-logo.png') }}" alt="ایثار" class="staff-auth-logo">
                <div class="staff-auth-brand-copy" style="text-align:right;">
                    <div style="font-size:20px;font-weight:700;color:#1e293b;">سامانه رزرو</div>
                    <div style="font-size:13px;color:#64748b;">ورود مدیران و کاربران</div>
                </div>
            </div>
        </div>

        <div class="staff-auth-card" x-data="staffAuthMorph()">
            @if(session('status'))
                <div class="staff-alert staff-alert-success" data-staff-fade>
                    <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
                </div>
            @endif

            {{-- Step 1: Mobile --}}
            @if($step === 'mobile')
                <div data-staff-flip="header">
                    <h2 class="staff-auth-heading">ورود به پنل</h2>
                    <p class="staff-auth-lead">شماره موبایل خود را وارد کنید</p>
                </div>

                <form wire:submit="submitMobile">
                    <div data-staff-flip="field-primary" class="staff-auth-field">
                        <label class="staff-auth-label">شماره موبایل</label>
                        <input type="tel" wire:model="mobile"
                               class="staff-auth-input @error('mobile') is-invalid @enderror"
                               placeholder="09xxxxxxxxx"
                               maxlength="11" dir="ltr" autofocus
                               style="text-align:center;font-size:18px;letter-spacing:2px;">
                        @error('mobile')<div class="staff-auth-error">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" data-staff-flip="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitMobile"><i class="bi bi-arrow-left me-2"></i>ادامه</span>
                        <span wire:loading wire:target="submitMobile">لطفاً صبر کنید...</span>
                    </button>
                </form>

            {{-- Step 2: Password --}}
            @elseif($step === 'password')
                <div data-staff-flip="header">
                    <h2 class="staff-auth-heading">رمز عبور</h2>
                    <p class="staff-auth-lead staff-auth-lead--tight">رمز عبور حساب</p>
                    <p class="staff-auth-mobile">{{ $mobile }}</p>
                </div>

                <form wire:submit="loginWithPassword">
                    <div data-staff-flip="field-primary" class="staff-auth-field">
                        <label class="staff-auth-label">رمز عبور</label>
                        <div class="staff-auth-password-wrap" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" wire:model="password"
                                   class="staff-auth-input @error('password') is-invalid @enderror"
                                   placeholder="رمز عبور خود را وارد کنید">
                            <button type="button" class="staff-auth-password-toggle" tabindex="-1"
                                    @click="show = !show" :title="show ? 'مخفی کردن' : 'نمایش'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        @error('password')<div class="staff-auth-error">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" data-staff-flip="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="loginWithPassword"><i class="bi bi-box-arrow-in-left me-2"></i>ورود</span>
                        <span wire:loading wire:target="loginWithPassword">در حال ورود...</span>
                    </button>
                </form>

                <div class="staff-auth-footer" data-staff-fade>
                    <button type="button" wire:click="backToMobile" class="staff-auth-link">
                        <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                    </button>
                </div>

            {{-- Step 3: OTP --}}
            @elseif($step === 'otp')
                <div data-staff-flip="header">
                    <h2 class="staff-auth-heading">تأیید شماره موبایل</h2>
                    <p class="staff-auth-lead staff-auth-lead--tight">کد ۶ رقمی ارسال شده به</p>
                    <p class="staff-auth-mobile staff-auth-mobile--otp">{{ $mobile }}</p>
                </div>

                <div class="staff-alert staff-alert-success staff-auth-test-hint" data-staff-fade>
                    <i class="bi bi-info-circle me-1"></i>کد تأیید به‌منظور نسخه تست: 123456
                </div>

                <form wire:submit="verifyOtp">
                    <div data-staff-flip="field-primary" class="staff-auth-field">
                        <label class="staff-auth-label">کد تأیید</label>
                        <input type="text" wire:model="otp"
                               class="staff-auth-input staff-auth-input--otp @error('otp') is-invalid @enderror"
                               placeholder="_ _ _ _ _ _"
                               maxlength="6" dir="ltr" autofocus inputmode="numeric">
                        @error('otp')<div class="staff-auth-error">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" data-staff-flip="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="verifyOtp"><i class="bi bi-check2-circle me-2"></i>تأیید</span>
                        <span wire:loading wire:target="verifyOtp">در حال تأیید...</span>
                    </button>
                </form>

                <div class="staff-auth-footer" data-staff-fade>
                    <button type="button" wire:click="resendOtp" class="staff-auth-link" wire:loading.attr="disabled">
                        <i class="bi bi-arrow-repeat me-1"></i>ارسال مجدد کد
                    </button>
                    <button type="button" wire:click="backToMobile" class="staff-auth-link">
                        <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                    </button>
                </div>

            {{-- Step 4: Set password (first-time) --}}
            @elseif($step === 'set_password')
                <div data-staff-flip="header">
                    <h2 class="staff-auth-heading">تنظیم رمز عبور</h2>
                    <p class="staff-auth-lead">برای ورودهای بعدی یک رمز عبور تعیین کنید</p>
                </div>

                <form wire:submit="setPassword">
                    <div data-staff-flip="field-primary" class="staff-auth-field">
                        <label class="staff-auth-label">رمز عبور جدید</label>
                        <div class="staff-auth-password-wrap" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" wire:model="password"
                                   class="staff-auth-input @error('password') is-invalid @enderror"
                                   placeholder="حداقل ۶ کاراکتر"
                                   autofocus>
                            <button type="button" class="staff-auth-password-toggle" tabindex="-1"
                                    @click="show = !show" :title="show ? 'مخفی کردن' : 'نمایش'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        @error('password')<div class="staff-auth-error">{{ $message }}</div>@enderror
                    </div>
                    <div data-staff-fade class="staff-auth-field staff-auth-field--secondary">
                        <label class="staff-auth-label">تکرار رمز عبور</label>
                        <div class="staff-auth-password-wrap" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                                   class="staff-auth-input"
                                   placeholder="رمز عبور را مجدداً وارد کنید">
                            <button type="button" class="staff-auth-password-toggle" tabindex="-1"
                                    @click="show = !show" :title="show ? 'مخفی کردن' : 'نمایش'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="staff-auth-btn" data-staff-flip="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="setPassword"><i class="bi bi-shield-check me-2"></i>ذخیره و ورود</span>
                        <span wire:loading wire:target="setPassword">در حال ذخیره...</span>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
