<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Messaging\Models\Shortcode;
use App\Modules\Accounts\Models\User;

try {
    $shortcode = Shortcode::where('shortcode', '20606')->first();
    if ($shortcode) {
        $shortcode->update([
            'client_account_id' => null,
            'is_dedicated' => false
        ]);
        echo "Shortcode 20606 successfully converted to Global Shared.\n";
    }

    // Ensure the first user (the admin) has SUPER_ADMIN role so God Mode works
    $admin = User::first();
    if ($admin) {
        $admin->update(['role_tier' => 'SUPER_ADMIN']);
        echo "Admin user ($admin->email) granted SUPER_ADMIN privileges.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
