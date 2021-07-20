<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Build;

use function exec;

/**
 * Class User
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class User {

    public function __construct(private string $name, private string $home) {
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    private static User $instance;

    /**
     * @return User
     */
    public static function getInstance(): User {
        return self::$instance ??= self::fromGlobals();
    }

    public static function fromGlobals() {
        $name = $_SERVER['USER'] ?? exec('whoami');
        $home = $_SERVER['HOME'] ?? exec('echo $HOME');

        return new self($name, $home);
    }

    /**
     * @return string
     */
    public function getHome(): string {
        return $this->home;
    }

    public function isRoot(): bool {
        return $this->name === 'root';
    }
}