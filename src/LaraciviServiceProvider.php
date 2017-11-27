<?php

namespace DMealy\Laracivi;

use Illuminate\Support\ServiceProvider;
use DMealy\Laracivi\CiviInstall;
use DMealy\Laracivi\CiviBootstrap;

class LaraciviServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(CiviInstall $installer, CiviBootstrap $civiBoot)
    {
        $installer->install();

        /**
         * civiBoot creates the civicrm.settings.php file.
         * When using homestead/vagrant, running composer update 
         * from the command line on the local machine (not homestead/vagrant ssh)
         * causes the directory settings generated for civicrm.settings.php to
         * refer to a path on the local machine, not the path on the homestead/vagrant webserver.
         * Solution is to either always run composer update from within homestead/vagrant ssh
         * or only run civiBoot->boot() when loading the app from the webserver
         * and not from the console.
         */
        // if (!$this->app->runningInConsole()) {
        //     $civiBoot->boot();
        // }

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
