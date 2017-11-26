<?php
namespace DMealy\Laracivi;

class Bootstrap
{
    public function __construct()
    {
    }

    public function boot()
    {
        $crmDir = base_path('vendor/dmealy/civicrm-core');
        ini_set('include_path', get_include_path() . PATH_SEPARATOR . $crmDir);
        $this->settings($crmDir);
        require_once(app_path('Civi/civicrm.settings.php'));
        require_once('api/class.api.php');
    }

    public static function settings($crmDir)
    {
        $tplPath = $crmDir . '/templates/CRM/common';
        $configFile = base_path('app/Civi') . '/civicrm.settings.php';
        $compileDir = $crmDir . '/templates_c';
        if (!file_exists($compileDir)) {
            mkdir($compileDir);
        }
        $configLogDir = $crmDir . '/ConfigAndLog';
        if (!file_exists($configLogDir)) {
            mkdir($configLogDir);
        }
        if (file_exists($configFile)) {
            return;
        }
        $dbUser = env('CIVI_DB_USERNAME', 'civiuser');
        $dbHost = env('CIVI_DB_HOST', '127.0.0.1');
        $dbName = env('CIVI_DB_DATABASE', 'civicrm');
        $dbPass = env('CIVI_DB_PASSWORD', 'secret');
        $params = [
            'crmRoot' => $crmDir,
            'templateCompileDir' => addslashes($compileDir),
            'frontEnd' => 0,
            'dbUser' => addslashes($dbUser),
            'dbPass' => addslashes($dbPass),
            'dbHost' => $dbHost,
            'dbName' => addslashes($dbName),
            'baseURL' => env('APP_URL', 'http://localhost'),
            'cms'   => env('CIVI_CMS', 'NoCms`'),
            'CMSdbUser' => addslashes($dbUser),
            'CMSdbPass' => addslashes($dbPass),
            'CMSdbHost' => $dbHost,
            'CMSdbName' => addslashes($dbName),
            'siteKey' => env('APP_KEY', md5(rand() . mt_rand() . rand() . uniqid('', true))),
        ];
        $configFile = base_path('app/Civi') . '/civicrm.settings.php';
        $tplRaw = file_get_contents($tplPath . '/civicrm.settings.php.template');
        foreach ($params as $key => $value) {
            $tplRaw = str_replace('%%' . $key . '%%', $value, $tplRaw);
        }
        file_put_contents($configFile, $tplRaw);
        return;
    }
}