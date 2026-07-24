<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Messaging\Models\IncomingMessage;

$latest = IncomingMessage::orderBy('id', 'desc')->take(5)->get();
echo "Latest MOs:\n";
foreach ($latest as $msg) {
    echo "ID: {$msg->id} | Client: {$msg->client_account_id} | Msg: {$msg->message} | Date: {$msg->created_at}\n";
}
