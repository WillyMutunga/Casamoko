<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Modules\Messaging\Controllers\ShortcodeController;
use App\Modules\Messaging\Models\Keyword;
use App\Modules\Messaging\Models\Shortcode;
use App\Modules\Accounts\Models\ClientAccount;

try {
    $client = ClientAccount::firstOrCreate(['company_name' => 'Test Company']);

    $shortcode = Shortcode::firstOrCreate(
        ['shortcode' => '20606'],
        ['is_dedicated' => false, 'status' => 'ACTIVE', 'network' => 'SAFARICOM']
    );

    $keyword = Keyword::firstOrCreate(
        ['shortcode_id' => $shortcode->id, 'client_account_id' => $client->id, 'keyword' => 'JOIN'],
        ['action_type' => 'OPT_IN']
    );

    $controller = new ShortcodeController();

    echo "--- TESTING NESTED MESSAGE PAYLOAD ---\n";
    $requestNested = Request::create('/api/mo-webhook', 'POST', [
        'smsServiceActivationNumber' => '20606',
        'senderAddress' => 'tel:254742765445',
        'message' => ['message' => 'JOIN']
    ]);
    
    $responseNested = $controller->handleSafaricomMO($requestNested);
    echo "Response (Nested): " . $responseNested->getContent() . "\n\n";
    
    echo "--- TESTING PLAIN MESSAGE PAYLOAD ---\n";
    $requestPlain = Request::create('/api/mo-webhook', 'POST', [
        'smsServiceActivationNumber' => '20606',
        'senderAddress' => 'tel:254742765445',
        'message' => 'JOIN'
    ]);
    
    $responsePlain = $controller->handleSafaricomMO($requestPlain);
    echo "Response (Plain): " . $responsePlain->getContent() . "\n\n";
    
    echo "Test Complete.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
