<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'gitauderrick911@gmail.com';
$user = App\Modules\Accounts\Models\User::where('email', $email)->first();

if ($user) {
    $user->delete();
    echo "Successfully deleted user with email: $email\n";
} else {
    echo "User with email $email not found in users table.\n";
}
