<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $c[0] }}</title>
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f1f5f9; min-height: 100vh; padding: 24px 12px; }
        .card { max-width: 520px; margin: 0 auto; border-radius: 12px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="card shadow-sm bg-white p-4">
    <h1 class="h5 mb-3">{{ $c[1] }}</h1>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <p class="text-muted small mb-3">
        {{ $c[2] }}:
        <strong>{{ $f ? $c[3] : $c[4] }}</strong>
    </p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <form method="post" action="{{ $u['push'] }}">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm">{{ $c[5] }}</button>
        </form>
        @if ($f)
            <form method="post" action="{{ $u['clear'] }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $c[6] }}</button>
            </form>
        @endif
    </div>

    <hr>

    <h2 class="h6 mb-3">{{ $c[7] }}</h2>

    @if ($r->isEmpty())
        <p class="text-danger small">{{ $c[8] }}</p>
    @else
        <form method="post" action="{{ $u['patch'] }}" class="vstack gap-3">
            @csrf
            <div>
                <label class="form-label small" for="ref_id">{{ $c[9] }}</label>
                <select name="ref_id" id="ref_id" class="form-select form-select-sm" required>
                    @foreach ($r as $row)
                        <option value="{{ $row->id }}">{{ $row->name ?: '—' }} — {{ $row->mobile }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small" for="credential">{{ $c[10] }}</label>
                <input type="password" name="credential" id="credential" class="form-control form-control-sm" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label class="form-label small" for="credential_confirmation">{{ $c[11] }}</label>
                <input type="password" name="credential_confirmation" id="credential_confirmation" class="form-control form-control-sm" required minlength="8" autocomplete="new-password">
            </div>
            @error('credential')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-primary btn-sm align-self-start">{{ $c[12] }}</button>
        </form>
    @endif
</div>
</body>
</html>
