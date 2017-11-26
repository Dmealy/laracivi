<?php
namespace DMealy\Laracivi;

use Illuminate\Config\Repository as Repository;

class DbConfig
{
    protected $config;
    protected $connectName;
    protected $connection;
    protected $package = 'dmealy/civicrm-core';

    public function __construct(Repository $config)
    {
        $this->config = $config;
        $this->connectName = env('CIVI_DB_CONNECTION');
        $this->setConnection();
    }

    public function connectionName()
    {
        return $this->connectName;
    }

    public function dbName()
    {
        return config("database.connections.{$this->connectName}.database");
    }

    public function packagePath()
    {
        return (base_path("vendor/{$this->package}"));
    }

    public function sqlPath()
    {
        return ($this->packagePath() . '/sql');
    }

    public function blankDbName()
    {
        $this->config->set("database.connections.{$this->connectName}.database", '');

        return $this;
    }

    public function restoreDbName()
    {
        $this->config->set("database.connections.{$this->connectName}.database", env('CIVI_DB_DATABASE'));

        return $this;
    }

    protected function setConnection()
    {
        if (!config("database.connections.{$this->connectName}")) {
            $this->config->set("database.connections.{$this->connectName}", array(
                'driver'    => 'mysql',
                'host'      => env('CIVI_DB_HOST'),
                'database'  => env('CIVI_DB_DATABASE'),
                'username'  => env('CIVI_DB_USERNAME'),
                'password'  => env('CIVI_DB_PASSWORD'),
                'port'      => env('CIVI_DB_PORT'),
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ));
        }
    }

}
