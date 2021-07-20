<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion;

/**
 * Class Completion
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class Completion {

    /**
     * @return CommandHandle
     */
    public function getCommandHandle(): CommandHandle {
        return $this->commandHandle;
    }

    public function __construct(protected CommandHandle $commandHandle, protected Argument $argument) {
    }

    /**
     * @return Argument
     */
    public function getArgument(): Argument {
        return $this->argument;
    }

    public function get(): ArrayObject {

        if ($this->argument->isOption()) {
            $data = $this->commandHandle->getOptions();
        } else {
            $data = $this->commandHandle->getCommands();
        }

        $data = $data->filter($this->argument->getName());

        return $data;
    }
}
