<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ورود پنل مدیریت | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ asset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .staff-auth-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        @media (max-width: 768px) and (orientation: portrait) {
            body.staff-auth-page {
                overflow: hidden;
                position: fixed;
                width: 100%;
                height: 100%;
            }
            .staff-auth-shell {
                position: fixed;
                inset: 0;
                align-items: flex-start;
                padding-top: max(24px, env(safe-area-inset-top));
                padding-bottom: max(24px, env(safe-area-inset-bottom));
                overflow-y: auto;
                overscroll-behavior: none;
                -webkit-overflow-scrolling: touch;
            }
        }
        .staff-auth-password-wrap {
            position: relative;
        }
        .staff-auth-password-wrap .staff-auth-input {
            padding-left: 44px;
        }
        .staff-auth-password-toggle {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            padding: 4px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
        .staff-auth-password-toggle:hover { color: #334155; }
        .staff-auth-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 32px;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
        .staff-auth-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color .2s;
        }
        .staff-auth-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        .staff-auth-btn {
            width: 100%;
            padding: 14px;
            background: #1e40af;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .staff-auth-btn:hover { background: #1e3a8a; }
        .staff-auth-btn:disabled { opacity: .6; cursor: not-allowed; }
        .staff-auth-link {
            color: #64748b;
            font-size: 13px;
            text-decoration: underline;
            background: none;
            border: none;
            cursor: pointer;
        }
        .staff-auth-link:hover { color: #334155; }
        .staff-alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .staff-alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .staff-alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
    @livewireStyles
</head>
<body class="staff-auth-page">
    {{ $slot }}
    @livewireScripts
</body>
</html>
