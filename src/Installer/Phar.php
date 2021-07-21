<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Installer;

use Medusa\EasyCompletion\Build\TempDir;
use Medusa\EasyCompletion\Cli;
use Medusa\EasyCompletion\EasyCompletion;
use function basename;
use function chmod;
use function dirname;
use function file_exists;
use function glob;
use function ini_get;
use function is_file;
use function unlink;
use const PHP_EOL;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

/**
 * Class Phar
 * @package Medusa\EasyCompletion\Installer
 * @author  Pascale Schnell <pascale.schnell@check24.de>
 */
class Phar {

    public function selfTest() {
        if (ini_get('phar.readonly')) {

            Cli::stdErr('creating archive disabled by the php.ini setting phar.readonly');
            $config = '/etc/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/cli/php.ini';
            if (is_file($config)) {
                $tee = 'echo "phar.readonly = Off" | sudo tee -a ' . $config;
                Cli::stdErr('Run: ');
                Cli::stdErr('    ' . $tee);
            }
            Cli::errorExit();
        }
    }

    public function create($entrypoint, $pharFile): void {

        $dir = dirname($entrypoint);

        $tmpDir = new TempDir($dir);
        $tmpDir->add(glob($dir . '/*'));

        // clean up
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        if (file_exists($pharFile . '.gz')) {
            unlink($pharFile . '.gz');
        }

        // create phar
        $phar = new \Phar($pharFile);

        // start buffering. Mandatory to modify stub to add shebang
        $phar->startBuffering();

        // Create the default stub from main.php entrypoint
        $defaultStub = $phar->createDefaultStub(basename($entrypoint));

        // Add the rest of the apps files

        $phar->buildFromDirectory($tmpDir->getBuildDir());

        // Customize the stub to add the shebang
        $stub = "#!/usr/bin/env " . EasyCompletion::DEFAULT_PHP_INTERPRETER . " \n" . $defaultStub;

        // Add the stub
        $phar->setStub($stub);

        $phar->stopBuffering();

        // plus - compressing it into gzip
        $phar->compressFiles(\Phar::GZ);

        # Make the file executable
        chmod($pharFile, 0755);

        $tmpDir->destroy();
        Cli::stdOut('pharfile at ' . $pharFile . ' successfully created' . PHP_EOL);
    }
}
