const fs = require('fs');

const apiPhpPath = 'backend/app/Modules/Messaging/routes/api.php';
let apiPhp = fs.readFileSync(apiPhpPath, 'utf8');

if (!apiPhp.includes('/process-queue')) {
  const routeCode = `
Route::get('/process-queue', function() {
    try {
        \\Illuminate\\Support\\Facades\\Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--timeout' => 120
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Queue processed successfully!',
            'output' => \\Illuminate\\Support\\Facades\\Artisan::output()
        ]);
    } catch (\\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
});
`;
  apiPhp = apiPhp.replace('<?php', '<?php\n' + routeCode);
  fs.writeFileSync(apiPhpPath, apiPhp);
  console.log('Added /process-queue route');
}
