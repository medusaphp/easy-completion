<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Build;

/**
 * Class BuildDefaultFunctions
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class BuildDefaultFunctions {

    public function load():void {
        require_once __DIR__ .'/DefaultExecFunctions.php';
    }
}
