<div>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ورود با بله</title>
    <link rel="icon" type="image/png" href="{{ asset('logo/site-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        :root {
            --brand: #FF385C;
            --dark: #222222;
            --muted: #717171;
            --line: #DDDDDD;
            --soft: #F7F7F7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(180deg, #fff 0%, #fff7f9 100%);
            color: var(--dark);
            min-height: 100vh;
        }
        .wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .cardx {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(0,0,0,.10);
            overflow: hidden;
        }
        .head {
            padding: 28px 24px 16px;
            text-align: center;
        }
        .logo {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 14px;
        }
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }
        .desc {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
        }
        .bodyx {
            padding: 0 24px 24px;
        }
        .status {
            border-radius: 16px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 13px;
            background: var(--soft);
            color: var(--dark);
            border: 1px solid var(--line);
        }
        .status.error { background: #fff0f3; border-color: #ffd3dc; color: #b4233d; }
        .status.success { background: #eefbf3; border-color: #cfeedd; color: #1f7a43; }
        .btn-brand {
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 16px;
            padding: 14px 16px;
            width: 100%;
            font-weight: 700;
            font-size: 15px;
            transition: transform .15s ease, background .15s ease;
        }
        .btn-brand:hover { background: #e82f54; transform: translateY(-1px); }
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 10px;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 13px 16px;
            background: #fff;
            color: var(--dark);
            font-weight: 600;
        }
        .hint {
            margin-top: 14px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.8;
        }
        .footer {
            padding: 16px 24px 24px;
            border-top: 1px solid var(--line);
            background: #fff;
        }
        .tiny {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.9;
            margin: 0;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f6f6f6;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 16px;
        }
        .spinner {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,.5);
            border-top-color: #fff;
            display: inline-block;
            animation: spin 1s linear infinite;
            vertical-align: -3px;
            margin-left: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="cardx">
        <div class="head">
            <div class="logo">
                <img src="{{ asset('logo/site-logo.png') }}" alt="لوگوی بنیاد">
            </div>
            <h1 class="title">ورود و ثبت‌نام با بله</h1>
            <p class="desc">مرجع رسمی رزرو اقامت و خدمات بنیاد شهید؛ با تخفیف ویژه برای خانواده‌های محترم عضو بنیاد شهید.</p>
        </div>

        <div class="bodyx">
            <div id="support-warning" class="status" style="display:none;"></div>
            <div id="result-box" class="status" style="display:none;"></div>

            <div class="badge">
                <i class="bi bi-shield-check"></i>
                <span>شماره فقط با رضایت کاربر از بله دریافت می‌شود</span>
            </div>

            <button id="share-phone" class="btn-brand" type="button">
                <span id="btn-text">دریافت شماره و ورود</span>
            </button>

            <div id="manual-phone-wrap" style="display:none;margin-top:12px;">
                <label for="manual-phone" style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;">شماره موبایل</label>
                <input id="manual-phone" type="tel" inputmode="numeric" placeholder="09xxxxxxxxx"
                       style="width:100%;border:1px solid var(--line);border-radius:16px;padding:13px 14px;font-family:inherit;font-size:15px;direction:ltr;text-align:center;">
                <button id="manual-submit" class="btn-ghost" type="button" style="margin-top:10px;">
                    <i class="bi bi-person-check"></i>
                    ارسال شماره و ورود
                </button>
            </div>

            <p class="hint">
                سامانه‌ای رسمی، امن و ساده برای رزرو آنلاین؛ با هدف ارائه دسترسی سریع‌تر به خدمات اقامتی، پشتیبانی قابل اعتماد و شرایط ویژه برای خانواده‌های معزز بنیاد شهید.
            </p>
        </div>
    </div>
</div>

<script src="https://tapi.bale.ai/miniapp.js?3"></script>
<script>
    const authUrl = @json($authUrl);
    const homeUrl = @json($homeUrl);
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const resultBox = document.getElementById('result-box');
    const warningBox = document.getElementById('support-warning');
    const btn = document.getElementById('share-phone');
    const btnText = document.getElementById('btn-text');
    const manualWrap = document.getElementById('manual-phone-wrap');
    const manualInput = document.getElementById('manual-phone');
    const manualSubmit = document.getElementById('manual-submit');

    function showMessage(message, type = '') {
        resultBox.className = 'status' + (type ? ' ' + type : '');
        resultBox.textContent = message;
        resultBox.style.display = 'block';
    }

    function showWarning(message) {
        warningBox.className = 'status error';
        warningBox.textContent = message;
        warningBox.style.display = 'block';
    }

    function setLoading(loading) {
        btn.disabled = loading;
        manualSubmit.disabled = loading;
        btnText.innerHTML = loading ? '<span class="spinner"></span>در حال دریافت شماره...' : 'دریافت شماره و ورود';
    }

    async function completeLogin(phone) {
        setLoading(true);

        try {
            const response = await fetch(authUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    init_data: window.Bale?.WebApp?.initData ?? '',
                    phone,
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                const message = payload.message || 'ورود انجام نشد.';
                showMessage(message, 'error');
                setLoading(false);
                return;
            }

            showMessage('ورود با موفقیت انجام شد. در حال انتقال...', 'success');

            setTimeout(() => {
                window.location.href = payload.redirect || homeUrl;
            }, 800);
        } catch (error) {
            showMessage('ارتباط با سرور برقرار نشد.', 'error');
            setLoading(false);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.Bale || !window.Bale.WebApp) {
            showWarning('این صفحه داخل کلاینت بله باز نشده است. از فرم ورود عادی استفاده کنید.');
            btn.disabled = true;
            manualWrap.style.display = 'block';
            return;
        }

        const webApp = window.Bale.WebApp;

        if (webApp.ready) {
            webApp.ready();
        }

        if (webApp.expand) {
            webApp.expand();
        }

        if (webApp.isMiniAppSupported === false) {
            showWarning('نسخه بله این کاربر از مینی‌اپ پشتیبانی نمی‌کند. لطفاً برنامه را به‌روزرسانی کند.');
            manualWrap.style.display = 'block';
        }

        btn.addEventListener('click', () => {
            if (!webApp.requestContact) {
                showMessage('این نسخه از بله از دریافت شماره پشتیبانی نمی‌کند.', 'error');
                return;
            }

            webApp.requestContact(async (sharedOrContact, maybePhone) => {
                // Bale may call back as (contactObject) or (shared, phone)
                let phone = null;

                if (typeof sharedOrContact === 'object' && sharedOrContact !== null) {
                    // Called as (contact) — contact object or null
                    phone = sharedOrContact.phone_number ?? sharedOrContact.phoneNumber ?? null;
                } else if (sharedOrContact === true || sharedOrContact === 1) {
                    // Called as (shared=true, phone)
                    phone = maybePhone ?? null;
                } else {
                    // shared=false or cancelled
                    showMessage('اشتراک شماره تلفن توسط کاربر لغو شد.', 'error');
                    manualWrap.style.display = 'block';
                    return;
                }

                if (!phone) {
                    showMessage('شماره تلفن دریافت نشد. لطفاً دستی وارد کنید.', 'error');
                    manualWrap.style.display = 'block';
                    return;
                }

                await completeLogin(phone);
            });
        });

        manualSubmit.addEventListener('click', async () => {
            const phone = manualInput.value.trim();

            if (!phone) {
                showMessage('شماره موبایل را وارد کنید.', 'error');
                return;
            }

            await completeLogin(phone);
        });
    });
</script>
</body>
</html>

</div>