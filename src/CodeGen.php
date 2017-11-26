<?php
namespace DMealy\Laracivi;

class CodeGen
{
    protected $civiRoot;

    public function __construct()
    {
        $this->civiRoot = base_path('vendor/dmealy/civicrm-core');
        $this->setPaths();
        $this->setMemoryLimit();
    }

    /**
     * Generate civicrm.mysql and other sql scripts in civicrm-core/sql.
     * Adopted from civicrm_core/xml/GenCode.php.
     *
     * @return null
     */
    public function generate()
    {
        require_once $this->civiRoot . '/CRM/Core/ClassLoader.php';
        \CRM_Core_ClassLoader::singleton()->register();

        /* To avoid running CRM_Core_CodeGen_Main from the civicrm-core/xml directory, these changes 
            need to be made to CRM_Core_CodeGen_I18n:
            1. line 16: file_get_contents('templates/languages.tpl', true)
            2. line 21: file_put_contents(base_path('vendor/civicrm/civicrm-core/') .'install/langs.php'
        */
        $cwd = getcwd();
        chdir($this->civiRoot . '/xml');

        $genCode = new \CRM_Core_CodeGen_Main(
            $this->civiRoot . '/CRM/Core/DAO/', // $CoreDAOCodePath
            $this->civiRoot . '/sql/', // $sqlCodePath
            $this->civiRoot . '/', // $phpCodePath
            $this->civiRoot . '/templates/', // $tplCodePath
            null, // IGNORE
            'NoCms', // framework - requires the NoCms classes included in dmealy/civicrm-core package.
            null, // db version
            $this->civiRoot . '/xml/schema/Schema.xml', // schema file
            null  // path to digest file
        );
        $genCode->main();
        chdir($cwd);
    }

    protected function setPaths()
    {
        date_default_timezone_set('UTC'); // avoid php warnings if timezone is not set - CRM-10844
        defined('CIVICRM_UF') or define('CIVICRM_UF', 'NoCms');  // Disregarded.
        defined('CIVICRM_UF_BASEURL') or define('CIVICRM_UF_BASEURL', '/');
        ini_set(
            'include_path',
            get_include_path()
            . PATH_SEPARATOR . $this->civiRoot
            . PATH_SEPARATOR . $this->civiRoot . '/xml'
        );
    }

    protected function setMemoryLimit()
    {
        // make sure the memory_limit is at least 512 MB
        $memLimitString = trim(ini_get('memory_limit'));
        $memLimitUnit = strtolower(substr($memLimitString, -1));
        $memLimit = (int) $memLimitString;
        switch ($memLimitUnit) {
            case 'g':
                $memLimit *= 1024;
                break;
            case 'm':
                $memLimit *= 1024;
                break;
            case 'k':
                $memLimit *= 1024;
                break;
        }
        if ($memLimit >= 0 and $memLimit < 536870912) {
            // Note: When processing all locales, CRM_Core_I18n::singleton() eats a lot of RAM.
            ini_set('memory_limit', -1);
        }
    }
}
