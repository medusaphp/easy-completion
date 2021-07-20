<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function call_user_func;
use function dd;
use function is_array;
use function is_bool;
use function is_callable;
use function var_dump;
use const INF;

/**
 * Class Completion
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ArgumentValueCompletion extends Completion {

    /**
     * Exit code / handled in completion bash
     * @const int
     */
    public const PATH_COMPLETION = 212;

    public function get(): ArrayObject {

        $index = count($this->argument->getValues()) - 1;
        $args = $this->commandHandle->getArguments();

        if (!is_callable($args)) {
            $completions = $args[$index] ?? null;
        } else {
            $completions = $args;
        }

        if (is_callable($completions)) {
            $completions = call_user_func($completions, $this->argument);
        } elseif ($completions === static::PATH_COMPLETION) {
            exit(static::PATH_COMPLETION);
        }

        if (is_array($completions)) {
            return (new ArrayObject($completions));
        }

        if (is_bool($completions)) {
            exit(0);
        }

        return $completions;
    }
}
