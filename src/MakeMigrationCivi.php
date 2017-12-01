<?php

namespace DMealy\Laracivi;

use Illuminate\Console\Command;
use DMealy\Laracivi\CiviMigrationGenerator;

class MakeMigrationCivi extends Command
{
    private $generator;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:migration:civi {--db=civicrm : Civicrm DB name (default is civicrm).}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Conver civicrm schem to migration files.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CiviMigrationGenerator $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info($this->generator->generate());
        //
    }
}
