<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Build;

use Medusa\EasyCompletion\Installer\Directory;
use function dirname;
use function is_file;

/**
 * Class TempDir
 * @package Medusa\EasyCompletion\Build
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class BuildDir extends TempDir {

    /**
     * TempDir constructor.
     * @param string $root
     */
    public function __construct(string $root) {

        if (is_file($root)) {
            $root = dirname($root);
        }

        $this->root = $root;
        $this->buildDir = $root . '/build';

        Directory::ensureExists($this->buildDir);
        Directory::ensureWriteable($this->buildDir);
    }
}
