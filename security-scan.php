#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/vendor/autoload.php';

use Fw\Console\Commands\SecurityScanCommand;

$scanner = new SecurityScanCommand(BASE_PATH);
$scanner->run();

echo $scanner->formatOutput();
exit($scanner->getExitCode());
