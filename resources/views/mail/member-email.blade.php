<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px 12px;background:#f6f7f6;font-family:system-ui,-apple-system,'Segoe UI',Roboto,'Hind Siliguri',sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;">
    <tr>
        <td style="padding:24px 24px 8px;font-size:13px;font-weight:700;color:#047857;">{{ config('app.name') }}</td>
    </tr>
    <tr>
        <td style="padding:0 24px;font-size:20px;font-weight:700;color:#0f172a;">{{ $messageSubject }}</td>
    </tr>
    <tr>
        <td style="padding:16px 24px 24px;font-size:15px;line-height:1.6;white-space:pre-line;">{{ $messageBody }}</td>
    </tr>
</table>
</body>
</html>
