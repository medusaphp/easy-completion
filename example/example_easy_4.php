<?php declare(strict_types = 1);

use Medusa\EasyCompletion\Argument;
use Medusa\EasyCompletion\EasyCompletion;

require_once __DIR__ . '/../vendor/autoload.php';

(new EasyCompletion(
    [
        'name' => 'my_easy',    // system binary "my_easy" should NOT exists
        'exec' => function() {  // because we will create an own executable

            var_dump(func_get_args());
            echo 'FOO BAR';
        },
    ], [
        'opt' => [
            '--arg_complete_test' => [
                'arg' => function(Argument $argument) {
                    return [$argument->getValue() . '_'];
                },
            ],

        ],
    ]
))->run();

// Test it:
// Usage: php ./example_easy_3.php [ARGUMENT_INDEX] [BINARY_NAME] ...[WORD_TO_COMPLETE]
// argument index and binary name are automatically added by the bash completion script. For testing you can use dummy values e.g. 99 dummy
// run:
// php ./example_easy_4.php 99 dummy --arg_complete_test = __
// php ./example_easy_4.php 99 dummy --arg_complete_test = __