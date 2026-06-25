const fs = require('fs');

const apiPhpPath = 'backend/app/Modules/Messaging/routes/api.php';
let apiPhp = fs.readFileSync(apiPhpPath, 'utf8');

if (!apiPhp.includes('/test-safaricom')) {
  const routeCode = `
Route::get('/test-safaricom', function() {
    $url = str_replace('8481', '9480', env('SAFARICOM_SDP_AUTH_URL', 'https://dsvc.safaricom.com:9480/api/auth/login'));
    $username = env('SAFARICOM_SDP_USERNAME');
    $password = env('SAFARICOM_SDP_PASSWORD');

    try {
        $response = \\Illuminate\\Support\\Facades\\Http::withHeaders([
            'accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Country' => 'KEN',
            'Content-Type' => 'application/json'
        ])->post($url, [
            'username' => $username,
            'password' => $password
        ]);

        return response()->json([
            'endpoint_tested' => $url,
            'username_used' => $username,
            'status_code' => $response->status(),
            'exact_safaricom_error' => $response->json() ?? $response->body()
        ]);
    } catch (\\Exception $e) {
        return response()->json([
            'endpoint_tested' => $url,
            'status_code' => 500,
            'exact_safaricom_error' => 'Connection Failed: ' . $e->getMessage()
        ]);
    }
});
`;
  apiPhp = apiPhp.replace('<?php', '<?php\n' + routeCode);
  fs.writeFileSync(apiPhpPath, apiPhp);
  console.log('Added /test-safaricom route');
}
