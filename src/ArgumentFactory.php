<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function str_starts_with;

/**
 * Class ArgumentFactory
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ArgumentFactory {

    public static function create(string $arg): Argument {

        $isOption = str_starts_with($arg, '-');
        $isAlias = $isOption && !str_starts_with($arg, '--');
        $arg = new Argument($arg, $isOption, $isAlias);

        return $arg;
    }
}
