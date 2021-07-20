<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function array_shift;
use function array_values;
use function count;

/**
 * Class ArgumentHandle
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ArgumentHandle {

    private int $argumentsCount;

    private array $arguments;

    public function __construct(array $arguments) {
        $arguments = array_values($arguments);
        $this->arguments = $arguments;
        $this->argumentsCount = count($this->arguments) - 1;
    }

    public static function createFromGlobals() {
        $arguments = $_SERVER['argv'];
        array_shift($arguments); // binary (self)
        return new static($arguments);
    }

    public function get(int $index): ?string {
        return $this->arguments[$index] ?? null;
    }

    public function drop(int $index): static {
        unset($this->arguments[$index]);
        $this->arguments = array_values($this->arguments);
        $this->argumentsCount = count($this->arguments) - 1;
        return $this;
    }

    public function getAll(): array {
        return $this->arguments;
    }
}
