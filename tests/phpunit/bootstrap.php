<?php
/**
 * PHPUnit bootstrap file
 *
 * - includes vendor/autoload.php
 */

$config = [];

$config['app_root'] = __DIR__.'/../../';
$config['autoload'] = "{$config['app_root']}/vendor/autoload.php";

require $config['autoload'];