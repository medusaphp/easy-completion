<?php declare(strict_types = 1);

namespace Medusa\EasyCompletion;

use function array_keys;
use function call_user_func;
use function implode;
use function is_callable;
use function is_numeric;
use function json_decode;

/**
 * Class PharFileCommandHandle
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class PharFileCommandHandle {

    public function __construct(private EasyCompletion $easyCompletion, private array $commands) {

    }

    public function run(ArgumentHandle $handle) {

        $commands = $this->commands;
        $cmd = $handle->get(0);

        if (is_numeric($cmd)) {
            return $this;
        }

        $command = $commands[$cmd] ?? null;

        if (!is_callable($command) || $cmd === 'help') {
            if ($cmd !== 'help') {
                Cli::stdErr('Unknown command "' . $cmd . '"');
            } else {
                Cli::stdErr('HELP');
            }
            Cli::stdErr('Possible commands are:');
            Cli::stdErr('    ' . implode("\n    ", array_keys($commands)));
            Cli::errorExit();
        }

        call_user_func($command, $this->easyCompletion);
        exit(0);
    }
}
