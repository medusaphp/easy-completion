<?php declare(strict_types = 1);

use Medusa\EasyCompletion\ArgumentValueCompletion;
use Medusa\EasyCompletion\EasyCompletion;

require_once __DIR__ . '/../vendor/autoload.php';

(new EasyCompletion(
    [
        'name' => 'my_easy',    // system binary "my_easy" should exists
    ], [
        'opt' => [
            '--dir' => [
                'arg'   => ArgumentValueCompletion::PATH_COMPLETION,
                'alias' => ['-d'],
            ],
        ],
        'cmd' => [
            'foobar1' => [
                'cmd' => [
                    'command2' => [],
                ],
            ],
            'foobar2' => [
                'opt' => [
                    '--long' => [
                        'alias' => '-s',
                    ],
                ],
            ],
            'foobar3' => [],
            'foo1bar' => [],
            'foo2bar' => [
                'opt' => [
                    '--verbose' => []
                ],
            ],
            'foo3bar' => [],
        ],
    ]
))->run();

// Test it:
// Usage: php ./example_easy_1.php [ARGUMENT_INDEX] [BINARY_NAME] ...[WORD_TO_COMPLETE]
// argument index and binary name are automatically added by the bash completion script. For testing you can use dummy values e.g. 99 dummy
// run:
//
// php ./example_easy_1.php 99 dummy foo
// php ./example_easy_1.php 99 dummy foobar1 ""

## !!!ArgumentValueCompletion::PATH_COMPLETION only works after the installation!!!