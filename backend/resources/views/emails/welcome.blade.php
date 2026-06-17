<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to Casamoko</title>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
        .header { background-color: #4f46e5; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; letter-spacing: 1px; }
        .content { padding: 30px; }
        .content p { font-size: 16px; line-height: 1.5; color: #cbd5e1; }
        .credentials { background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 15px; margin: 20px 0; }
        .credentials p { margin: 5px 0; font-family: monospace; color: #e2e8f0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
        .btn { display: inline-block; background-color: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Casamoko!</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->name }},</p>
            <p>Your account has been successfully provisioned on the Casamoko platform as a <strong>{{ str_replace('_', ' ', $user->sub_role ?? $user->role_tier) }}</strong>.</p>
            
            <p>You can now log in and access your workspace.</p>
            
            @if($plainPassword)
            <div class="credentials">
                <p><strong>Login URL:</strong> {{ config('app.url') }}</p>
                <p><strong>Email:</strong> {{ $user->email }}</p>
                <p><strong>Temporary Password:</strong> {{ $plainPassword }}</p>
            </div>
            <p><em>Please ensure you change your password upon your first login.</em></p>
            @endif
            
            <center>
                <a href="{{ config('app.url') }}" class="btn">Log in to Dashboard</a>
            </center>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Casamoko CPaaS. All rights reserved.
        </div>
    </div>
</body>
</html>
