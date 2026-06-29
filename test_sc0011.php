<?php
// Script to login and trigger a quick send
$url = 'https://casamoko.co.ke/api/auth/login';
$credentials = json_encode(['email' => 'wmutunga003@gmail.com', 'password' => 'William#20']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $credentials);
$response = curl_exec($ch);
curl_close($ch);

$auth = json_decode($response, true);
if (!isset($auth['token'])) {
    die("Login failed: " . $response . "\n");
}
$token = $auth['token'];

echo "Logged in successfully. Token: " . substr($token, 0, 10) . "...\n";

// Now send a quick message
$sendUrl = 'https://casamoko.co.ke/api/messaging/quick-send';
$payload = json_encode([
    'recipient' => '0742765445',
    'message' => 'Test SC0011 error',
    'sender_id' => 'CASAMOKO' // assuming a default sender ID
]);

$ch2 = curl_init($sendUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
$sendResponse = curl_exec($ch2);
curl_close($ch2);

echo "Quick Send Response:\n";
echo $sendResponse . "\n";
