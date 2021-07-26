<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Installer;

use Medusa\EasyCompletion\Build\BuildDir;
use Medusa\EasyCompletion\Build\ExtractCallable;
use Medusa\EasyCompletion\Build\TempDir;
use Medusa\EasyCompletion\Build\User;
use Medusa\EasyCompletion\Cli;
use Medusa\EasyCompletion\EasyCompletion;
use function array_filter;
use function array_map;
use function basename;
use function chmod;
use function copy;
use function date;
use function define;
use function defined;
use function dirname;
use function explode;
use function file;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function get_included_files;
use function getcwd;
use function glob;
use function implode;
use function in_array;
use function is_callable;
use function is_file;
use function is_readable;
use function json_decode;
use function preg_replace;
use function realpath;
use function str_replace;
use function str_starts_with;
use function touch;
use function trim;
use const MDS_EASY_COMPLETION_INSTALL_MODE;
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
        private string $cwd,
        private ?string $exec
    ) {
    }

    public static function fromGlobals(string $name, string|null|callable $exec, bool $createExecFile = true) {

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
            if ($createExecFile) {
                file_put_contents($installDirCompletionPhar . '/' . $fileName, $extractedClosure);
                Cli::stdOut('executable at ' . $installDirCompletionPhar . '/' . $fileName . ' successfully created' . PHP_EOL);
            }
            $exec = EasyCompletion::DEFAULT_PHP_INTERPRETER . ' ' . $installDirCompletionPhar . '/' . $fileName;
        }

        $self = new self($user, $installDirCompletionPhar, $installDirCompletionBash, $name, getcwd(), $exec);
        return $self;
    }

    public function createInstaller(): void {
        $phar = new Phar();
        $phar->selfTest();

        $entrypoint = realpath($_SERVER['SCRIPT_NAME'] ?? get_included_files()[0]);

        $dir = dirname($entrypoint);

        $configFile = $dir . '/mec_installer.json';
        $config = [];
        if (is_file($configFile) && is_readable($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        }
        $pharFileInstaller = $this->cwd . '/' . $this->name . '_installer.phar';
        $ignoreFiles = array_map(function(string $ignoreFile) use ($dir) {
            if ($ignoreFile[0] === '/') {
                return $ignoreFile;
            }
            return $dir . '/' . $ignoreFile;
        }, $config['ignoreFiles'] ?? []);
        $ignoreFiles[] =  $pharFileInstaller;
        $tmpDirRoot = new TempDir($dir);
        $pharFile = $tmpDirRoot->getBuildDir() . '/completion.phar';
        $phar->create($entrypoint, $pharFile, $ignoreFiles);

        $tmpDir2 = new BuildDir($tmpDirRoot->getBuildDir());
        $tmpDir2->add(glob($dir . '/*'), $dir, $ignoreFiles);

        $entryPointInstaller = $tmpDirRoot->getBuildDir() . '/installer_entry.php';
        file_put_contents($entryPointInstaller, str_replace(
            [
                '{{ NAME }}',
                '{{ BUILD_DIR }}',
                '{{ ENTRYPOINT }}',
            ], [
                $this->name,
                basename($tmpDir2->getBuildDir()),
                basename($entrypoint),
            ],
            file_get_contents(__DIR__ . '/installer_entry.php')));

        $phar->create($entryPointInstaller, $pharFileInstaller);
        $tmpDirRoot->destroy();
    }

    public function run() {

        $pharFile = $this->getPathToPharFile();

        if (!defined('MDS_EASY_COMPLETION_INSTALL_MODE')) {
            $entryPoint = realpath($_SERVER['SCRIPT_NAME'] ?? get_included_files()[0]);
            $phar = new Phar();
            $phar->selfTest();
            $phar->create($entryPoint, $pharFile);
        } else {
            copy(MDS_EASY_COMPLETION_INSTALL_MODE, $pharFile);
            define('MDS_EASY_COMPLETION_PHAR_TARGET', $pharFile);
            chmod($pharFile, 0755);
            Cli::stdOut('pharfile at ' . $pharFile . ' successfully created' . PHP_EOL);
        }

        $this->createBashCompletionSh($pharFile);

        if (!$this->exec) {
            $this->cleanupAlias();
            return;
        }

        $this->updateAlias();
    }

    public function getPathToPharFile() {
        return $this->installDirCompletionPhar . '/' . $this->name . '.phar';
    }

    private function createBashCompletionSh(string $pharFile, ?string $completionShTarget = null): void {
        $interpreter = EasyCompletion::DEFAULT_PHP_INTERPRETER;
        $tpl = file_get_contents(__DIR__ . '/completer_tpl.sh');
        $tpl = str_replace(
            [
                '{{ name }}',
                '{{ executable }}',
            ], [
                $this->name,
                $interpreter . ' ' . $pharFile,
            ], $tpl);

        $completionShTarget ??= $this->installDirCompletionBash . '/' . $this->name;
        Cli::stdOut('bash completion at ' . $completionShTarget . ' successfully created' . PHP_EOL);
        file_put_contents($completionShTarget, $tpl);
    }

    private function cleanupAlias() {

        if ($this->user->isRoot()) {
            $easyCompletions = '/etc/easy_completion/ec_alias';
        } else {
            $easyCompletions = $this->user->getHome() . '/.easy_completions';
        }

        if (!file_exists($easyCompletions)) {
            return;
        }

        $easyCompletionsContent = array_filter(array_map('trim', file($easyCompletions)));
        $easyCompletionsContentFiltered = array_filter($easyCompletionsContent, fn(string $row) => !str_starts_with(trim($row), 'alias ' . $this->name . '="'));

        if ($easyCompletionsContentFiltered === $easyCompletionsContent) {
            Cli::stdOut('no cleanup for alias list at ' . $easyCompletions . ' needed' . PHP_EOL);
            return;
        }

        file_put_contents($easyCompletions, implode(PHP_EOL, $easyCompletionsContentFiltered) . PHP_EOL);
        Cli::stdOut('remove old alias for ' . $this->name . ' at ' . $easyCompletions . PHP_EOL);
        Cli::stdOut('Pleas run:' . PHP_EOL);
        Cli::stdOut('   unalias ' . $this->name . PHP_EOL);
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
            Cli::stdOut('Pleas run:' . PHP_EOL);
            Cli::stdOut('    source ' . $localBashRc . PHP_EOL);
        } else {
            Cli::stdOut('no update for alias list at ' . $easyCompletions . ' needed' . PHP_EOL);
        }
    }

    private function doBackup(string $file) {
        $backup = $file . '_' . date('YmdHis') . '.bak';
        copy($file, $backup);
    }
}