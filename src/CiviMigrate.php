<?php

namespace DMealy\Laracivi;

use Illuminate\Console\Command;
use DMealy\Laracivi\Migration;

class CiviMigrate extends Command
{
    private $migration;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'civi:migrate {--db=civicrm : Civicrm DB name (default is civicrm).}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate civicrm tables to database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Migration $migration)
    {
        parent::__construct();
        $this->migration = $migration;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info($this->migration->migrate());
        //
    }
}
