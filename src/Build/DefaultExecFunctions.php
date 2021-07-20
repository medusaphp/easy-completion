<?php declare(strict_types = 1);

function mecExec(string $cmd):int {

    $fp = popen($cmd, 'r');
    while (!feof($fp)) {
        echo fread($fp, 1024);
    }
    return pclose($fp);
}
