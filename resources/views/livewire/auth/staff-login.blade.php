<div class="staff-auth-shell">
    <div style="width:100%;max-width:440px;">
        <div style="text-align:center;margin-bottom:28px;">
            <div style="display:inline-flex;align-items:center;gap:10px;">
                <div style="width:44px;height:44px;background:#1e40af;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-buildings-fill" style="color:#fff;font-size:22px;"></i>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:20px;font-weight:700;color:#1e293b;">سامانه رزرو</div>
                    <div style="font-size:13px;color:#64748b;">ورود مدیران و میزبان‌ها</div>
                </div>
            </div>
        </div>

        <div class="staff-auth-card">
            @if(session('status'))
                <div class="staff-alert staff-alert-success">
                    <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
                </div>
            @endif

            {{-- Step 1: Mobile --}}
            @if($step === 'mobile')
                <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:6px;">ورود به پنل</h2>
                <p style="font-size:14px;color:#64748b;margin-bottom:24px;">شماره موبایل خود را وارد کنید</p>

                <form wire:submit="submitMobile">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">شماره موبایل</label>
                        <input type="tel" wire:model="mobile"
                               class="staff-auth-input @error('mobile') is-invalid @enderror"
                               placeholder="09xxxxxxxxx"
                               maxlength="11" dir="ltr" autofocus
                               style="text-align:center;font-size:18px;letter-spacing:2px;">
                        @error('mobile')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitMobile"><i class="bi bi-arrow-left me-2"></i>ادامه</span>
                        <span wire:loading wire:target="submitMobile">لطفاً صبر کنید...</span>
                    </button>
                </form>

            {{-- Step 2: Password --}}
            @elseif($step === 'password')
                <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:6px;">رمز عبور</h2>
                <p style="font-size:14px;color:#64748b;margin-bottom:4px;">رمز عبور حساب</p>
                <p style="font-size:16px;font-weight:700;color:#1e293b;direction:ltr;margin-bottom:24px;">{{ $mobile }}</p>

                <form wire:submit="loginWithPassword">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">رمز عبور</label>
                        <div class="staff-auth-password-wrap" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" wire:model="password"
                                   class="staff-auth-input @error('password') is-invalid @enderror"
                                   placeholder="رمز عبور خود را وارد کنید"
                                   autofocus>
                            <button type="button" class="staff-auth-password-toggle" tabindex="-1"
                                    @click="show = !show" :title="show ? 'مخفی کردن' : 'نمایش'">
                                <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        @error('password')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="loginWithPassword"><i class="bi bi-box-arrow-in-left me-2"></i>ورود</span>
                        <span wire:loading wire:target="loginWithPassword">در حال ورود...</span>
                    </button>
                </form>

                <div style="text-align:center;margin-top:20px;display:flex;flex-direction:column;gap:10px;">
                    <button type="button" wire:click="switchToOtp" class="staff-auth-link" wire:loading.attr="disabled">
                        <i class="bi bi-phone me-1"></i>ورود با رمز یکبار مصرف
                    </button>
                    <button type="button" wire:click="backToMobile" class="staff-auth-link">
                        <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                    </button>
                </div>

            {{-- Step 3: OTP --}}
            @elseif($step === 'otp')
                <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:6px;">تأیید شماره موبایل</h2>
                <p style="font-size:14px;color:#64748b;margin-bottom:4px;">کد ۶ رقمی ارسال شده به</p>
                <p style="font-size:16px;font-weight:700;color:#1e293b;direction:ltr;margin-bottom:20px;">{{ $mobile }}</p>

                <div class="staff-alert staff-alert-success" style="font-size:12px;">
                    <i class="bi bi-info-circle me-1"></i>کد تأیید به‌منظور نسخه تست: 123456
                </div>

                <form wire:submit="verifyOtp">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">کد تأیید</label>
                        <input type="text" wire:model="otp"
                               class="staff-auth-input @error('otp') is-invalid @enderror"
                               placeholder="_ _ _ _ _ _"
                               maxlength="6" dir="ltr" autofocus inputmode="numeric"
                               style="text-align:center;font-size:24px;letter-spacing:8px;font-weight:700;">
                        @error('otp')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="staff-auth-btn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="verifyOtp"><i class="bi bi-check2-circle me-2"></i>تأیید</span>
                        <span wire:loading wire:target="verifyOtp">در حال تأیید...</span>
                    </button>
                </form>

                <div style="text-align:center;margin-top:20px;display:flex;flex-direction:column;gap:10px;">
                    <button type="button" wire:click="resendOtp" class="staff-auth-link" wire:loading.attr="disabled">
                        <i class="bi bi-arrow-repeat me-1"></i>ارسال مجدد کد
                    </button>
                    <button type="button" wire:click="backToMobile" class="staff-auth-link">
                        <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                    </button>
                </div>

            {{-- Step 4: Set password (first-time) --}}
            @elseif($step === 'set_password')
                <h2 style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:6px;">تنظیم رمز عبور</h2>
                <p style="font-size:14px;color:#64748b;margin-bottom:24px;">برای ورودهای بعدی یک رمز عبور تعیین کنید</p>

                <form wire:submit="setPassword">
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">رمز عبور جدید</label>
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
                        @error('password')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:6px;">تکرار رمز عبور</label>
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
                    <button type="submit" class="staff-auth-btn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="setPassword"><i class="bi bi-shield-check me-2"></i>ذخیره و ورود</span>
                        <span wire:loading wire:target="setPassword">در حال ذخیره...</span>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
