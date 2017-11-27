<?php
namespace DMealy\Laracivi;

use DMealy\Laracivi\CiviBootstrap;

class Laracivi
{
    protected $civiApi;

    public function __construct(CiviBootstrap $civiBoot)
    {
        $civiBoot->boot();
    }

    public function api()
    {
        $this->civiApi = new \civicrm_api3();

        return $this->civiApi;
    }
    
}