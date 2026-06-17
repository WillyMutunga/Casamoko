<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Update</title>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
        .header { background-color: #f59e0b; padding: 20px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 24px; letter-spacing: 1px; }
        .content { padding: 30px; }
        .content p { font-size: 16px; line-height: 1.5; color: #cbd5e1; }
        .details { background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 15px; margin: 20px 0; }
        .details p { margin: 5px 0; font-family: monospace; color: #e2e8f0; font-size: 14px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Statement Available</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->name }},</p>
            <p>A new invoice or billing update has been generated for your Casamoko account.</p>
            
            <div class="details">
                <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Amount Due:</strong> KES {{ number_format($invoice->total_amount, 2) }}</p>
                <p><strong>Status:</strong> {{ $invoice->status }}</p>
                <p><strong>Issue Date:</strong> {{ \Carbon\Carbon::parse($invoice->created_at)->format('d M Y') }}</p>
            </div>
            
            <p>Please log into your dashboard to view the full PDF and complete payment if necessary.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Casamoko CPaaS. All rights reserved.
        </div>
    </div>
</body>
</html>
