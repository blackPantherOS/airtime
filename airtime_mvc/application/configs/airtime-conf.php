<?php
// This file generated by Propel 1.5.2 convert-conf target
// from XML runtime conf file /home/martin/workspace/airtime/airtime_mvc/build/runtime-conf.xml
$conf = array (
  'datasources' => 
  array (
    'airtime' => 
    array (
      'adapter' => 'pgsql',
      'connection' => 
      array (
        'dsn' => 'pgsql:host=localhost;port=5432;dbname=airtime;user=airtime;password=airtime',
      ),
    ),
    'default' => 'airtime',
  ),
  'generator_version' => '1.5.2',
);
$conf['classmap'] = include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classmap-airtime-conf.php');
return $conf;