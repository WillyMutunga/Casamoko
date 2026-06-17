<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $modulesPath = app_path('Modules');

        if (!file_exists($modulesPath)) {
            return;
        }

        // Find all subdirectories in app/Modules
        $modules = array_map('basename', glob($modulesPath . '/*', GLOB_ONLYDIR));

        foreach ($modules as $module) {
            // Load routes if they exist in app/Modules/{Module}/routes/api.php
            $routesPath = $modulesPath . '/' . $module . '/routes/api.php';
            if (file_exists($routesPath)) {
                Route::prefix('api')
                    ->middleware('api')
                    ->namespace("App\\Modules\\{$module}\\Controllers")
                    ->group($routesPath);
            }

            // Load migrations if they exist in app/Modules/{Module}/Migrations
            $migrationsPath = $modulesPath . '/' . $module . '/Migrations';
            if (file_exists($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
