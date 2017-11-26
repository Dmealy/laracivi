<?php
namespace DMealy\Laracivi;

use Illuminate\Support\Facades\Schema;
use DMealy\Laracivi\DbConfig;
use DB;

class Migration
{
    protected $civiConfig;

    public function __construct(DbConfig $config)
    {
        $this->civiConfig = $config;
    }

    public function migrate()
    {
        if ($this->dbExists()) {
            $dbName = $this->civiConfig->dbName();
            return "Database '{$dbName}' already exists.  No changes made.";
        }

        $this->createDb();

        // Create and seed tables.
        // TBD: Language-specific seeders (civicrm_data.en_US.mysql) should be used if present
        $conn = $this->civiConfig->connectionName();
        $sqlSrc = ['civicrm.mysql', 'civicrm_data.mysql', 'civicrm_acl.mysql'];
        foreach ($sqlSrc as $src) {
            $queries = preg_split('/;\s*$/m', $this->cleanSql($src));
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    DB::connection($conn)->statement($query);
                }
            }
        }

        return 'Civicrm tables created.';
    }

    protected function cleanSql($src)
    {
        $sqlRaw = file_get_contents($this->civiConfig->sqlPath() . '/' . $src);
        // Source: civicrm-core/install/civicrm.php
        // change \r\n to fix windows issues
        $sqlRaw = str_replace("\r\n", "\n", $sqlRaw);
        //get rid of comments starting with # and --
        $sqlRaw = preg_replace("/^#[^\n]*$/m", "\n", $sqlRaw);
        $sqlRaw = preg_replace("/^(--[^-]).*/m", "\n", $sqlRaw);

        return $sqlRaw;
    }

    protected function dbExists()
    {
        // Remove db name from connection to avoid sql errors if db does not yet exist.
        $dbName = $this->civiConfig->dbName();
        $this->civiConfig->blankDbName();
        $conn = $this->civiConfig->connectionName();
        $result = DB::reconnect($conn)->select(
            "select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = :name",
            ['name' => $dbName]
        );
        $this->civiConfig->restoreDbName();

        return (!empty($result));
    }

    protected function createDb()
    {
        // Create database and reload connection.
        $dbName = $this->civiConfig->dbName();
        $this->civiConfig->blankDbName();
        $conn = $this->civiConfig->connectionName();
        DB::reconnect($conn)->statement('create database if not exists ' . $dbName);
        $this->civiConfig->restoreDbName();
        DB::reconnect($conn);
    }
}
