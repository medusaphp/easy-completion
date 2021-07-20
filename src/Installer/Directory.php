<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Installer;

use Medusa\EasyCompletion\Cli;
use function is_dir;
use function is_readable;
use function is_writable;
use function mkdir;

/**
 * Class Directory
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Directory {

    /**
     * @param string $completionPath
     */
    public static function ensureReadable(string $completionPath): void {
        if (!is_readable($completionPath)) {
            Cli::stdErr('directory isn´t readable');
            Cli::errorExit();
        }
    }

    /**
     * @param string $completionPath
     */
    public static function ensureWriteable(string $completionPath): void {

        if (!is_writable($completionPath)) {
            Cli::stdErr('directory isn´t readable');
            Cli::errorExit();
        }
    }

    /**
     * @param string $completionPath
     */
    public static function ensureExists(string $completionPath): void {
        if (!is_dir($completionPath)) {
            mkdir($completionPath, 0755, true);
            if (!is_dir($completionPath)) {
                Cli::stdErr('Something went wrong. Could not create directory at ' . $completionPath);
                Cli::errorExit();
            }
        }
    }
}
