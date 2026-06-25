<?php

require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$gateway = app(\App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway::class);

echo "Testing Safaricom Gateway...\n";
echo "Fetching Balance...\n";
$balance = $gateway->getBalance();
echo "Balance: " . ($balance ?? 'Failed to get balance') . "\n";

echo "\nSending SMS to 254742765445...\n";
$response = $gateway->send('254742765445', 'Test message from Antigravity Diagnostics', 'CASAMOKO');
print_r($response);
