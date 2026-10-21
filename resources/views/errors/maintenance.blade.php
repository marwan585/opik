<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Maintenance — {{ config('app.name', 'Cloud Server') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, 'Segoe UI', Roboto, 'IBM Plex Sans', sans-serif;
            background: radial-gradient(circle at 20% 30%, #1a2332 0%, #0a0e14 70%);
            color: #ecf0f1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { text-align: center; max-width: 540px; width: 100%; }
        .icon-wrap {
            display: inline-flex; align-items: center; justify-content: center;
            width: 100px; height: 100px; border-radius: 24px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.18) 0%, rgba(6, 182, 212, 0.12) 100%);
            border: 1px solid rgba(129, 140, 248, 0.3);
            margin-bottom: 28px;
        }
        .icon-wrap svg {
            width: 48px; height: 48px; color: #818cf8;
            animation: spin 4s linear infinite;
        }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        h1 {
            font-size: 36px; font-weight: 800; color: #fff;
            margin-bottom: 12px; letter-spacing: -0.02em;
        }
        p { font-size: 16px; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 999px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.08em;
            margin-bottom: 20px;
        }
        .badge::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: #fbbf24; animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .footer { margin-top: 36px; font-size: 12px; color: #475569; }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">Mode Maintenance</div>
        <div class="icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
            </svg>
        </div>
        <h1>Sedang Maintenance</h1>
        <p>{!! nl2br(e($message)) !!}</p>
        <p style="font-size:14px;color:#64748b;">Silakan hubungi admin jika Anda butuh akses mendesak.</p>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'Cloud Server') }}
        </div>
    </div>
</body>
</html>
