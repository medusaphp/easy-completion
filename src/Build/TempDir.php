<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Build;

use Medusa\EasyCompletion\Installer\Directory;
use function dirname;
use function exec;
use function is_file;
use function is_numeric;
use function is_string;
use function md5;
use function microtime;
use function mt_rand;
use function strlen;
use function substr;
use const PHP_EOL;

/**
 * Class TempDir
 * @package Medusa\EasyCompletion\Build
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class TempDir {

    private static int $cnt = 0;
    protected string   $buildDir;
    protected string   $root;

    /**
     * TempDir constructor.
     * @param string $root
     */
    public function __construct(string $root) {

        if (is_file($root)) {
            $root = dirname($root);
        }

        $this->root = $root;
        $this->buildDir = $root . '/._build' . (self::$cnt++) . substr(md5(microtime(true) . '#' . mt_rand(1, 1000)), 0, 8);

        Directory::ensureExists($this->buildDir);
        Directory::ensureWriteable($this->buildDir);
    }

    public function add(array|string $files, ?string $directoryPrefixToRemove = null): void {

        if (is_string($files)) {
            $files = [$files];
        }

        $rootStrLength = strlen($this->root);

        if ($directoryPrefixToRemove) {
            $rootStrLength = strlen($directoryPrefixToRemove);
        }

        foreach ($files as $index => $resource) {

            if (is_numeric($index)) {
                $target = $this->buildDir . '/' . substr($resource, $rootStrLength + 1);
            } else {
                $target = $this->buildDir . '/' . $resource;
                $resource = $index;
            }

            Directory::ensureExists(dirname($target));
            Directory::ensureWriteable(dirname($target));
            exec('cp -R ' . $resource . ' ' . $target);
        }
    }

    public function destroy(): void {
        exec('rm -rf ' . $this->buildDir);
    }

    /**
     * @return string
     */
    public function getBuildDir(): string {
        return $this->buildDir;
    }
}
