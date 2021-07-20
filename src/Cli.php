<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function fwrite;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Class Cli
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Cli {

    public static function errorExit(): void {
        exit(1);
    }

    public static function stdErr(string $msg): void {
        fwrite(STDERR, $msg . PHP_EOL);
    }

    public static function stdOut(string $msg): void {
        fwrite(STDOUT, $msg);
    }
}
