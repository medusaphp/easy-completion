<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Installer;

use Medusa\EasyCompletion\Build\ExtractCallable;
use Medusa\EasyCompletion\Build\User;
use Medusa\EasyCompletion\Cli;
use Medusa\EasyCompletion\EasyCompletion;
use Phar;
use function array_filter;
use function basename;
use function chmod;
use function copy;
use function date;
use function dirname;
use function exec;
use function explode;
use function file;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function get_included_files;
use function implode;
use function is_callable;
use function md5;
use function microtime;
use function mt_rand;
use function preg_replace;
use function realpath;
use function str_replace;
use function strlen;
use function substr;
use function touch;
use function trim;
use function unlink;
use const PHP_EOL;

/**
 * Class Installer
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Installer {

    public function __construct(
        private User $user,
        private string $installDirCompletionPhar,
        private string $installDirCompletionBash,
        private string $name,
        private ?string $exec
    ) {
    }

    public static function fromGlobals(string $name, string|null|callable $exec) {

        $user = User::getInstance();

        if ($user->isRoot()) {
            $installDirCompletionBash = '/etc/bash_completion.d';
            $installDirCompletionPhar = '/etc/easy_completion';
        } else {
            $installDirCompletionBash = $user->getHome() . '/.local/share/bash-completion/completions';
            $installDirCompletionPhar = $user->getHome() . '/.local/share/easy_completion';
        }

        Directory::ensureExists($installDirCompletionBash);
        Directory::ensureWriteable($installDirCompletionBash);
        Directory::ensureExists($installDirCompletionPhar);

        if (is_callable($exec)) {
            $extractedClosure = ExtractCallable::do($exec);
            $fileName = $name . '.fn.php';
            file_put_contents($installDirCompletionPhar . '/' . $fileName, $extractedClosure);
            Cli::stdOut('executable at ' . $installDirCompletionPhar . '/' . $fileName . ' successfully created' . PHP_EOL);
            $exec = EasyCompletion::DEFAULT_PHP_INTERPRETER . ' ' . $installDirCompletionPhar . '/' . $fileName;
        }

        $self = new self($user, $installDirCompletionPhar, $installDirCompletionBash, $name, $exec);
        return $self;
    }

    public function run() {

        $entryPoint = realpath($_SERVER['SCRIPT_NAME'] ?? get_included_files()[0]);
        $pharFile = $this->getPathToPharFile();

        // create build directory

        $this->createPhar($entryPoint, $pharFile);
        $tpl = file_get_contents(__DIR__ . '/completer_tpl.sh');
        $tpl = str_replace(
            [
                '{{ name }}',
                '{{ executeable }}',
            ], [
                $this->name,
                $pharFile,
            ], $tpl);

        $completionShTarget = $this->installDirCompletionBash . '/' . $this->name;
        Cli::stdOut('bash completion at ' . $completionShTarget . ' successfully created' . PHP_EOL);
        file_put_contents($completionShTarget, $tpl);

        if (!$this->exec) {
            return;
        }

        $this->updateAlias();
    }

    public function getPathToPharFile() {
        return $this->installDirCompletionPhar . '/' . $this->name . '.phar';
    }

    public function createPhar($entrypoint, $pharFile) {

        $dir = dirname($entrypoint);
        $buildDir = $dir . '/._build' . substr(md5(microtime(true) . '#' . mt_rand(1, 1000)), 0, 8);

        Directory::ensureExists($buildDir);
        Directory::ensureWriteable($buildDir);

        $originResources = glob($dir . '/*');

        foreach ($originResources as $resource) {
            $target = $buildDir . '/' . substr($resource, strlen($dir) + 1);
            Directory::ensureExists(dirname($target));
            Directory::ensureWriteable(dirname($target));
            exec('cp -R ' . $resource . ' ' . $target);
        }

        $dir = $buildDir;

        // clean up
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        if (file_exists($pharFile . '.gz')) {
            unlink($pharFile . '.gz');
        }

        // create phar
        $phar = new Phar($pharFile);

        // start buffering. Mandatory to modify stub to add shebang
        $phar->startBuffering();

        // Create the default stub from main.php entrypoint
        $defaultStub = $phar->createDefaultStub(basename($entrypoint));

        // Add the rest of the apps files

        $phar->buildFromDirectory($dir);

        // Customize the stub to add the shebang
        $stub = "#!/usr/bin/env " . EasyCompletion::DEFAULT_PHP_INTERPRETER . " \n" . $defaultStub;

        // Add the stub
        $phar->setStub($stub);

        $phar->stopBuffering();

        // plus - compressing it into gzip
        $phar->compressFiles(Phar::GZ);

        # Make the file executable
        chmod($pharFile, 0755);

        exec('rm -rf ' .$buildDir);
        Cli::stdOut('pharfile at ' . $pharFile . ' successfully created' . PHP_EOL);
    }

    private function updateAlias() {
        // Handle bashrc or alias
        if ($this->user->isRoot()) {
            $localBashRc = '/etc/bash.bashrc';
            $easyCompletions = '/etc/easy_completion/ec_alias';
        } else {
            $localBashRc = $this->user->getHome() . '/.bashrc';
            $easyCompletions = $this->user->getHome() . '/.easy_completions';
        }

        if (!file_exists($easyCompletions)) {
            touch($easyCompletions);
        }
        if (!file_exists($localBashRc)) {
            touch($localBashRc);
        }

        $localBashRcContent = file($localBashRc);
        $easyCompletionsContent = file($easyCompletions);
        $easyCompletionsAlreadySourced = false;

        $programAlias = 'alias ' . $this->name . '="' . $this->exec . '"';

        foreach ($localBashRcContent as $row) {
            if (trim($row) === '. ' . $easyCompletions) {
                $easyCompletionsAlreadySourced = true;
                break;
            }
        }

        if (!$easyCompletionsAlreadySourced) {
            $this->doBackup($localBashRc);
            $localBashRcContent[] = '. ' . $easyCompletions . PHP_EOL;
            file_put_contents($localBashRc, implode('', $localBashRcContent));
        }

        $aliasExists = false;
        $needsSave = false;
        foreach ($easyCompletionsContent as &$row) {
            $row = trim($row);

            if ($row === $programAlias) {
                $aliasExists = true;
                break;
            }

            $prep = preg_replace('/^alias\ /', '', trim($row));
            if ($prep === $row) {
                continue;
            }

            $cmd = explode('=', $prep, 2)[0];

            if ($cmd !== $this->name) {
                continue;
            }

            $needsSave = true;
            $row = null;
        }

        unset($row);

        if ($needsSave || !$aliasExists) {
            if (!$aliasExists) {
                $easyCompletionsContent[] = $programAlias;
            }
            $easyCompletionsContent = array_filter($easyCompletionsContent);
            $easyCompletionsContent[] = '';
            file_put_contents($easyCompletions, implode(PHP_EOL, $easyCompletionsContent));
            Cli::stdOut('alias list at ' . $easyCompletions . ' successfully updated' . PHP_EOL);
            Cli::stdOut('run: source ' . $localBashRc . PHP_EOL);
        } else {
            Cli::stdOut('no update for alias list at ' . $easyCompletions . ' needed' . PHP_EOL);
        }
    }

    private function doBackup(string $file) {
        $backup = $file . '_' . date('YmdHis') . '.bak';
        copy($file, $backup);
    }
}