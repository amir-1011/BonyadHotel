<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>در حال بروزرسانی | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
        }
        .maintenance-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .maintenance-card {
            max-width: 480px;
            width: 100%;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 48px 32px;
            background: #fff;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .08);
        }
        .maintenance-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #1e40af;
        }
        .maintenance-title {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .maintenance-text {
            font-size: 15px;
            line-height: 1.9;
            color: #64748b;
            margin-bottom: 0;
        }
        .maintenance-spinner {
            margin-top: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 13px;
        }
        .maintenance-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #3b82f6;
            animation: pulse 1.4s ease-in-out infinite;
        }
        .maintenance-dot:nth-child(2) { animation-delay: .2s; }
        .maintenance-dot:nth-child(3) { animation-delay: .4s; }
        @keyframes pulse {
            0%, 80%, 100% { opacity: .3; transform: scale(.8); }
            40% { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="maintenance-shell">
        <div class="maintenance-card">
            <div class="maintenance-icon">
                <i class="bi bi-tools"></i>
            </div>
            <h1 class="maintenance-title">سامانه در حال بروزرسانی است</h1>
            <p class="maintenance-text">
                در حال اعمال تغییرات و بهبود سامانه رزرو هستیم.
                <br>
                لطفاً چند دقیقه دیگر مراجعه کنید.
            </p>
            <div class="maintenance-spinner" aria-hidden="true">
                <span class="maintenance-dot"></span>
                <span class="maintenance-dot"></span>
                <span class="maintenance-dot"></span>
            </div>
        </div>
    </div>
</body>
</html>
