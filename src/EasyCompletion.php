<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use Medusa\EasyCompletion\Build\BuildDefaultFunctions;
use Medusa\EasyCompletion\Build\ExtractCallable;
use Medusa\EasyCompletion\Installer\Installer;
use Phar;
use function array_map;
use function array_pop;
use function array_shift;
use function array_slice;
use function array_splice;
use function array_values;
use function call_user_func;
use function count;
use function defined;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function mecExec;
use function stream_get_meta_data;
use function tmpfile;
use const INF;
use const PHP_EOL;

/**
 * Class EasyCompletion
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class EasyCompletion {

    public const DEFAULT_PHP_INTERPRETER = 'php8.0';

    private ArgumentHandle $argumentHandle;

    private string $name;

    private ?PharFileCommandHandle $pharFileCommandHandle = null;

    public function __construct(private string|array $command, private array $map) {
        $this->name = is_array($this->command) ? $this->command['name'] : $this->command;
    }

    /**
     * @return array|mixed|string
     */
    public function getName(): mixed {
        return $this->name;
    }

    public function commandsForPharFile(array $commands): static {
        $this->pharFileCommandHandle = new PharFileCommandHandle($this, $commands);
        return $this;
    }

    /**
     * @return array
     */
    public function getMap(): array {
        return $this->map;
    }

    /**
     * Set Map
     * @param array $map
     * @return EasyCompletion
     */
    public function setMap(array $map): EasyCompletion {
        $this->map = $map;
        return $this;
    }

    public function run(?callable $fn = null): void {

        if (defined('MDS_EASY_COMPLETION_INSTALL_MODE')) {
            $this->install();
            return;
        }

        $this->argumentHandle = ArgumentHandle::createFromGlobals();

        $isPhar = Phar::running() !== '';

        if (!$isPhar) {
            $arg = $this->argumentHandle->get(0);
            if (!in_array($arg, ['--install', '--test', '--test-phar', '--create-installer', '--test-exec'])) {
                Cli::stdErr('First argument must be one of --install, --test, --test-phar, --create-installer, --test-exec');
                Cli::errorExit();
            }

            if ($arg === '--install') {
                $this->install();
                return;
            } elseif ($arg === '--create-installer') {
                $this->createInstaller();
                return;
            } elseif ($arg === '--test-exec') {
                $this->testExec();
                return;
            } elseif ($arg !== '--test-phar') {
                $this->pharFileCommandHandle = null;
            }
            $this->argumentHandle->drop(0);
        }

        $this->pharFileCommandHandle?->run($this->argumentHandle);

        if ($isPhar) {
            $arguments = $this->argumentHandle->getAll();
            $argumentIndexToComplete = (int)array_shift($arguments); // Which argument should be completed
            array_shift($arguments); // binary from autocompletionscript
            array_splice($arguments, (int)$argumentIndexToComplete);
            $this->argumentHandle = new ArgumentHandle($arguments);
        }

        if ($fn) {
            call_user_func($fn, $this);
        }

        $all = $this->argumentHandle->getAll();

        $lastEntry = array_pop($all);
        $lastArg = ArgumentFactory::create($lastEntry ?? '');
        $completion = $this->determineCompletionHandle($all, $lastArg);

        if (!$completion) {
            return;
        }

        if ($completion->getArgument()->isOption()) {
            if ($completion->getArgument()->getValue() === $lastArg->getName()) {
                $lastArg = $completion->getArgument();
            }
        }

        $commandHandle = $completion->getCommandHandle();
        $data = $completion->get();
        $suffix = '';
        if (!($completion instanceof ArgumentValueCompletion)) {
            $suffix = ' ';
        }

        if ($data->count() === 1) {
            $argToComplete = ArgumentFactory::create($data->get(0));
            if ($argToComplete->isOption() && $commandHandle->getNeededArguments($argToComplete) && $argToComplete->isAlias() === false) {
                $suffix = '=';
            }
        }
        $exit = 0;
        if ($lastArg->isOption() && $lastArg->getValue()) {
            $suffix = '';
            $exit = 128;
        }

        $data = $data->withSuffix($suffix);

        if ($exit) {
            Cli::stdOut(implode("\t", array_map('trim', $data->toArray())));
        } else {
            Cli::stdOut(implode("\n", $data->toArray()));
        }

        exit($exit);
    }

    public function install() {
        $this->validate($this->map);
        $name = is_array($this->command) ? $this->command['name'] : $this->command;
        $exec = is_array($this->command) ? ($this->command['exec'] ?? null) : null;
        $installer = Installer::fromGlobals($name, $exec);
        $installer->run();
    }

    private function validate(array $commands): void {

        $opts = $commands['opt'] ?? [];
        $args = $commands['arg'] ?? [];
        $cmds = $commands['cmd'] ?? [];
        $alias = $commands['alias'] ?? [];

        if (!is_array($opts)) {
            Cli::stdErr('Options "opt" must be an array');
            Cli::errorExit();
        }
        if (!is_array($args)) {
            Cli::stdErr('Arguments "arg" must be an array');
            Cli::errorExit();
        }
        if (!is_array($cmds)) {
            Cli::stdErr('Commands "cmd" must be an array');
            Cli::errorExit();
        }
        if (!is_array($alias)) {
            Cli::stdErr('Alias "alias" must be an array');
            Cli::errorExit();
        }

        foreach ($cmds as $cmd) {
            $this->validate($cmd);
        }

        foreach ($args as $arg) {
            if ($arg === true) {
                continue;
            } elseif (is_callable($arg)) {
                continue;
            } elseif (is_int($arg)) {
                continue;
            }

            Cli::stdErr('Argument callback must be callable, boolean true or an integer');
            Cli::errorExit();
        }

        foreach ($opts as $opt) {
            $this->validate($opt);
        }
    }

    protected function createInstaller() {
        $this->validate($this->map);
        $name = is_array($this->command) ? $this->command['name'] : $this->command;
        $exec = is_array($this->command) ? ($this->command['exec'] ?? null) : null;
        $installer = Installer::fromGlobals($name, $exec, false);
        $installer->createInstaller();
    }

    public function testExec() {
        $exec = is_array($this->command) ? $this->command['exec'] : null;

        if (is_callable($exec)) {
            $content = ExtractCallable::do($exec);
            $file = tmpfile();
            $path = stream_get_meta_data($file)['uri'];
            file_put_contents($path, $content);
            $exec = self::DEFAULT_PHP_INTERPRETER . ' ' . $path;
            $args = array_slice($_SERVER['argv'], 2);
            $args = array_map('escapeshellarg', $args);
            $exec .= ' ' . implode(' ', $args);
        }

        if (is_string($exec)) {
            (new BuildDefaultFunctions())->load();
            $code = mecExec($exec);
            Cli::stdOut(PHP_EOL . 'RETURNED ERROR CODE: ' . $code . PHP_EOL);
        }
    }

    /**
     * @param array    $all
     * @param Argument $lastArg
     * @return Completion|null
     */
    public function determineCompletionHandle(array $all, Argument $lastArg): ?Completion {

        $allIncludeLastArg = $all;
        $allIncludeLastArg[] = $lastArg->getName();

        $commandHandle = new CommandHandle($this->map);
        for ($i = 0, $max = count($all); $i < $max; $i++) {
            $arg = ArgumentFactory::create($all[$i]);
            $neededArguments = $commandHandle->getNeededArguments($arg);

            if (!$neededArguments) {
                if ($arg->isOption()) {
                    $commandHandle = $commandHandle->withoutOption($arg);
                } else {
                    $commandHandle = $commandHandle->forward($arg);
                }
                continue;
            }

            if (!$arg->isAlias()) {
                $i++;

                if ($i === $max) {
                    // Last arg must be an equal sign if option is not an alias

                    if ($lastArg->getName() === '=') {
                        $arg->addValue('');
                        return new ArgumentValueCompletion($commandHandle->forward($arg), $arg);
                    }

                    $lastArg = $arg;
                    break;
                }
            }

            if (!$arg->isOption()) {
                $commandHandle = $commandHandle->forward($arg);
            }

            if (is_callable($neededArguments)) {
                $neededArgCount = INF;
            } else {
                $neededArgCount = count($neededArguments);
            }

            for ($neededArgCounter = 0; $neededArgCounter < $neededArgCount; $neededArgCounter++) {
                while (true) {

                    $i++;
                    if ($i < $max) {

                        $tmp1 = $allIncludeLastArg[$i + 1] ?? null;
                        $tmp2 = $all[$i + 1] ?? null;

                        if ($tmp1 === ':') {

                            if ($tmp2 === null) {
                                $lastArg->setName($all[$i] . ':');
                                $arg->addValue($lastArg->getName());
                                if ($arg->isOption()) {
                                    $commandHandle = $commandHandle->forward($arg);
                                }
                                return new ArgumentValueCompletion($commandHandle, $arg);
                            } else {
                                $all[$i] .= ':' . ($all[$i + 2] ?? '');
                                if (($all[$i + 2] ?? null) === null) {
                                    $last = $all[$i] . $lastArg->getName();
                                    $lastArg->setName($last);
                                    $arg->addValue($lastArg->getName());
                                    if ($arg->isOption()) {
                                        $commandHandle = $commandHandle->forward($arg);
                                    }
                                    return new ArgumentValueCompletion($commandHandle, $arg);
                                }

                                unset($all[$i + 1], $all[$i + 2]);
                                $all = array_values($all);
                                $last = array_pop($allIncludeLastArg);
                                $allIncludeLastArg = $all;
                                $allIncludeLastArg[] = $last;
                                $max = count($all);
                            }
                        }
                    }

                    if ($i < $max) {

                        $nextArg = ArgumentFactory::create($all[$i]);
                        if ($nextArg->isOption()) {

                            $optionNeedsArguments = $commandHandle->getNeededArguments($nextArg);
                            if ($optionNeedsArguments && $arg->isOption()) {
                                break;
                            }
                            // Dont resolve recursive optionarguments!
                            if ($optionNeedsArguments) {
                                foreach ($optionNeedsArguments as $optionNeededArgument) {
                                    $i++;
                                    if ($i < $max) {
                                        $optArg = ArgumentFactory::create($all[$i]);
                                        $nextArg->addValue($optArg->getName());
                                    } else {
                                        $nextArg->addValue($lastArg->getName());
                                        $commandHandle = $commandHandle->forward($nextArg);

                                        return new ArgumentValueCompletion($commandHandle, $nextArg);
                                    }
                                }
                            }
                            $commandHandle = $commandHandle->withoutOption($nextArg);
                        } else {
                            $arg->addValue($nextArg->getName());
                            break;
                        }
                        continue;
                    }

                    if ($i >= $max) {

                        if (!$lastArg->isOption()) {
                            $arg->addValue($lastArg->getName());
                            if ($arg->isOption()) {
                                $commandHandle = $commandHandle->forward($arg);
                            }

                            return new ArgumentValueCompletion($commandHandle, $arg);
                        }
                    }
                    break 2;
                }
            }

            if ($arg->isOption()) {
                $commandHandle = $commandHandle->withoutOption($arg);
            }
        }

        return new Completion($commandHandle, $lastArg);
    }
}
