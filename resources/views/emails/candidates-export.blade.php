<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body style="font-family: sans-serif; color: #1a1a1a; line-height: 1.5;">
    <p>{!! nl2br(e($messageBody)) !!}</p>

    <p style="margin-top: 2rem; color: #6b7280; font-size: 0.875rem;">
        Sent from {{ config('app.name') }}.
    </p>
</body>
</html>
