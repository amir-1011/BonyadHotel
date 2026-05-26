

<div style="min-height:calc(100vh - 130px);display:flex;align-items:center;justify-content:center;padding:40px 16px;">
    <div style="width:100%;max-width:440px;">
        {{-- Logo --}}
        <div style="text-align:center;margin-bottom:28px;">
            <a href="{{ route('home') }}" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><path fill="#FF385C" d="M16 1C9.4 1 4 7.4 4 15.2c0 4.8 2.4 9.2 6 12.2L16 31l6-3.6c3.6-3 6-7.4 6-12.2C28 7.4 22.6 1 16 1zm0 22c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8z"/></svg>
                <span style="font-size:22px;font-weight:700;color:#FF385C;">ایثار</span>
            </a>
        </div>
        <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:32px;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.06);">
            <h2 style="font-size:20px;font-weight:700;color:var(--bnb-dark);margin-bottom:6px;">خوش آمدید به ایثار</h2>
            <p style="font-size:14px;color:var(--bnb-gray);margin-bottom:24px;">شماره موبایل خود را وارد کنید</p>



            @if($errors->any())
            <div class="bnb-alert bnb-alert-danger mb-4">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif
            <form action="{{ route('auth.send-otp') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label class="bnb-label">شماره موبایل</label>
                    <input type="tel" name="mobile"
                           class="bnb-select @error('mobile') is-invalid @enderror"
                           placeholder="09xxxxxxxxx"
                           value="{{ old('mobile') }}"
                           maxlength="11" dir="ltr" autofocus
                           style="text-align:center;font-size:18px;letter-spacing:2px;">
                    @error('mobile')<div style="color:var(--bnb-red);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn-bnb w-100" style="font-size:15px;padding:14px;">
                    <i class="bi bi-send me-2"></i>ارسال کد تأیید
                </button>
            </form>
            <div style="border-top:1px solid var(--bnb-border);margin-top:20px;padding-top:16px;text-align:center;">
                <p style="font-size:12px;color:var(--bnb-gray);">
                    با ادامه، شما با <a href="#" style="color:var(--bnb-dark);font-weight:600;">شرایط خدمات</a> و <a href="#" style="color:var(--bnb-dark);font-weight:600;">سیاست حریم خصوصی</a> موافقت می‌کنید.
                </p>
            </div>
        </div>
    </div>
</div>
