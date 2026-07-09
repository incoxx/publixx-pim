<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $collectionName ?? 'Geschützter Link' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            background: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            width: 100%;
            max-width: 360px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            padding: 28px 24px;
        }
        .card h1 { font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 4px; }
        .card p.subtitle { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
        .card label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .card input[type="password"] {
            width: 100%;
            padding: 9px 12px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
        .card button {
            width: 100%;
            margin-top: 16px;
            padding: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #fff;
            background: #2563eb;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .card button:hover { background: #1d4ed8; }
        .error {
            margin-bottom: 16px;
            padding: 10px 12px;
            font-size: 12px;
            color: #b91c1c;
            background: #fef2f2;
            border-radius: 8px;
        }
        .expired-icon { font-size: 32px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if ($expired)
            <div class="expired-icon">⏳</div>
            <h1>Link abgelaufen</h1>
            <p class="subtitle">Dieser Freigabe-Link ist nicht mehr gültig. Bitte wenden Sie sich an den Absender für einen neuen Link.</p>
        @else
            <h1>{{ $collectionName }}</h1>
            <p class="subtitle">Dieser Link ist passwortgeschützt.</p>

            @if ($error ?? false)
                <div class="error">Falsches Passwort. Bitte erneut versuchen.</div>
            @endif

            <form method="POST" action="{{ url()->current() }}">
                @csrf
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" autofocus required>
                <button type="submit">Freigeben</button>
            </form>
        @endif
    </div>
</body>
</html>
