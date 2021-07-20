<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function array_keys;
use function call_user_func;
use function is_array;
use function is_callable;

/**
 * Class CommandHandle
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class CommandHandle {

    /**
     * CommandHandle constructor.
     * @param array $map
     */
    public function __construct(private array $map) {

        // Build complete options map
        $opt = $this->map['opt'] ?? [];
        $combined = [];
        $optionMap = [];

        foreach ($opt as $name => $setting) {

            $optAliasOrNames = $setting['alias'] ?? [];
            unset($setting['alias']);
            $optAliasOrNames[] = $name;
            foreach ($optAliasOrNames as $optAliasOrName) {
                $combined[$optAliasOrName] = $name;
            }
            $optionMap[$name] = $setting;
        }

        $this->map['optPrepared'] = $combined;
        $this->map['optMap'] = $optionMap;
    }

    public function getOptions(): ArrayObject {
        return new ArrayObject(array_keys($this->map['optPrepared']));
    }

    public function getArguments(): array {
        return $this->map['arg'] ?? [];
    }

    public function getNeededArguments(Argument $argument): array {

        if ($argument->isOption()) {
            $basename = $this->map['optPrepared'][$argument->getName()] ?? null;
            if ($basename === null) {
                return [];
            }
            return $this->map['optMap'][$basename]['arg'] ?? [];
        }

        return $this->map['cmd'][$argument->getName()]['arg'] ?? [];
    }

    public function withoutOption(Argument $arg): static {
        $tmp = $this->map;

        if ($arg->isOption()) {
            $basename = $this->map['optPrepared'][$arg->getName()] ?? null;
            if ($basename) {
                unset($tmp['opt'][$basename]);
            }
        } else {
            unset($tmp['opt'][$arg->getName()]);
        }
        return new static($tmp);
    }

    public function forward(Argument $arg): static {

        if ($arg->isOption()) {
            $basename = $this->map['optPrepared'][$arg->getName()] ?? null;
            if ($basename === null) {
                return new static([]);
            }

            return new static($this->map['optMap'][$basename] ?? []);
        }

        return new static($this->map['cmd'][$arg->getName()] ?? []);
    }

    public function getCommands(): ArrayObject {

        $cmd = array_keys($this->map['cmd'] ?? []);

        if (!empty($this->map['cmdFn']) && is_callable($this->map['cmdFn'])) {

            $addCmd = call_user_func($this->map['cmdFn']);
            $prepared = [];
            foreach ($addCmd as $name => $value) {

                if (!is_array($value)) {
                    $cmd[] = $value;
                } else {
                    $cmd[] = $name;
                }
            }
        }

        return new ArrayObject($cmd);
    }
}
