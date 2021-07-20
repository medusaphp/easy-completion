<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function str_starts_with;

/**
 * Class ArrayObject
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ArrayObject {

    private int   $cnt;
    private array $data;

    public function __construct(array $data) {
        $data = array_filter($data, fn($val) => $val !== '');
        $this->data = array_values($data);
        $this->cnt = count($this->data);
    }

    public function count(): int {
        return $this->cnt;
    }

    public function get(int $index): mixed {
        return $this->data[$index] ?? null;
    }

    /**
     * @return array
     */
    public function toArray(): array {
        return $this->data;
    }

    public function withSuffix(string $suffix): static {
        return new self(array_map(fn($package) => $package . $suffix, $this->data));
    }

    public function withPrefix(string $prefix): static {
        return new self(array_map(fn($package) => $prefix . $package, $this->data));
    }

    public function filter(string|array $filterVal, bool $filterIfNotMatch = true): static {
        return new self(array_filter($this->data, fn($value) => $filterIfNotMatch === str_starts_with($value, $filterVal)));
    }

    public function filterMatch(string|array $filterVal, bool $filterIfNotMatch = true): static {

        if (!is_array($filterVal)) {
            $filterVal = [$filterVal];
        }
        if ($filterIfNotMatch) {
            return new self(array_intersect($this->data, $filterVal));
        }
        return new self(array_diff($this->data, $filterVal));
    }
}
