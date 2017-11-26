<?php

namespace DMealy\Laracivi;

use Illuminate\Support\ServiceProvider;
use DMealy\Laracivi\CiviInstall;

class LaraciviServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(CiviInstall $installer)
    {
        $this->publishes([
            __DIR__.'/src/civicrm.php' => config_path('civicrm.php'),
        ]);

        $installer->install();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CiviMigrate::class,
            ]);
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
