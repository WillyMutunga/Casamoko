<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$consumerKey = env('MPESA_CONSUMER_KEY');
$consumerSecret = env('MPESA_CONSUMER_SECRET');
$shortcode = env('MPESA_SHORTCODE');
$passkey = env('MPESA_PASSKEY');
$callbackUrl = env('MPESA_CALLBACK_URL');
$phone = '254742765445';
$amount = 10;

echo "Consumer Key: $consumerKey\n";
echo "Shortcode: $shortcode\n";

$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$authResponse = Http::withHeaders([
    'Authorization' => 'Basic ' . $credentials,
])->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

if (!$authResponse->successful()) {
    echo "Auth Failed:\n" . $authResponse->body() . "\n";
    exit;
}

$accessToken = $authResponse->json()['access_token'];
echo "Auth Success! Token: $accessToken\n";

$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);

$stkPayload = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => 'Casamoko',
    'TransactionDesc' => 'Wallet Topup'
];

echo "Sending STK Payload...\n";
print_r($stkPayload);

$stkResponse = Http::withHeaders([
    'Authorization' => 'Bearer ' . $accessToken,
    'Content-Type' => 'application/json',
])->post('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $stkPayload);

echo "STK Response Status: " . $stkResponse->status() . "\n";
echo "STK Response Body:\n" . $stkResponse->body() . "\n";
