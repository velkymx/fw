<?php

declare(strict_types=1);

return [
    // Driver: 'sync', 'file', or 'database'
    'driver' => 'file',

    // File driver settings
    'path' => BASE_PATH . '/storage/queue',

    // Database driver settings
    'table' => 'jobs',

    // Default queue name
    'default' => 'default',
];
