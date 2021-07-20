<?php declare(strict_types = 1);

use Medusa\EasyCompletion\Argument;
use Medusa\EasyCompletion\EasyCompletion;

foreach ([
             __DIR__ . '/../vendor/autoload.php',
             __DIR__ . '/vendor/autoload.php',
         ] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

### START EXEC EXPORT ###
function thisFunctionWillBeAvailableInTheExecutableFile() {
    echo 'DO WHATEVER YOU WANT' . PHP_EOL;
}

### STOP EXEC EXPORT ###

(new EasyCompletion(
    [
        'name' => 'my_easy_test',    // system binary "my_easy_test" should NOT exists
        'exec' => function() {       // because we will create an own executable
            thisFunctionWillBeAvailableInTheExecutableFile();
        },
    ], [
        'opt' => [
            '--arg_complete_test' => [
                'arg' => function(Argument $argument) {
                    return [$argument->getValue() . '_'];
                },
            ],

        ],
        'cmd' => [
            'foo' => [],
            'bar' => [],
        ],
    ]
))->install();

// Test it:
// See README.md