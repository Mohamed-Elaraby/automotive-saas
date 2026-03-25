<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Critical Admin Notification</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
<h2 style="margin-bottom: 8px;">Critical Admin Notification</h2>

<p><strong>Title:</strong> {{ $notification->title }}</p>
<p><strong>Type:</strong> {{ strtoupper(str_replace('_', ' ', $notification->type)) }}</p>
<p><strong>Severity:</strong> {{ strtoupper($notification->severity) }}</p>
<p><strong>Message:</strong> {{ $notification->message ?: '-' }}</p>
<p><strong>Tenant:</strong> {{ $notification->tenant_id ?: '-' }}</p>
<p><strong>User Email:</strong> {{ $notification->user_email ?: '-' }}</p>
<p><strong>Notified At:</strong> {{ optional($notification->notified_at)->format('Y-m-d H:i:s') ?: '-' }}</p>

<h4 style="margin-top: 24px;">Context</h4>
<pre style="background: #f6f6f6; padding: 12px; border: 1px solid #ddd; white-space: pre-wrap;">{{ json_encode($notification->context_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
</body>
</html>
