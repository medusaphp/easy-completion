<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

/**
 * Class Argument
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Argument {

    private array $values = [];

    public function __construct(
        private string $name,
        private bool $option,
        private bool $alias
    ) {

    }

    /**
     * Set Name
     * @param string $name
     * @return Argument
     */
    public function setName(string $name): Argument {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string {
        return $this->values[0] ?? null;
    }

    /**
     * @return array
     */
    public function getValues(): array {
        return $this->values;
    }

    /**
     * Set Value
     * @param string|null $value
     * @return Argument
     */
    public function addValue(?string $value): Argument {
        $this->values[] = $value;
        return $this;
    }


    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isOption(): bool {
        return $this->option;
    }

    /**
     * @return bool
     */
    public function isAlias(): bool {
        return $this->option ? $this->alias : true;
    }
}
