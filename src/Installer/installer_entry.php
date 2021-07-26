<?php declare(strict_types = 1);

use Medusa\EasyCompletion\Cli;

define('MDS_EASY_COMPLETION_INSTALL_MODE', __DIR__ . '/completion.phar');

$buildDir = __DIR__ . '/{{ BUILD_DIR }}';

//copy(__DIR__ . '/{{ BUILD_DIR }}/completion.phar', '{{ PHAR_TARGET }}.phar');
require_once __DIR__ . '/{{ BUILD_DIR }}/{{ ENTRYPOINT }}';

$mecJson = __DIR__ . '/{{ BUILD_DIR }}/mec_installer.json';

if (!is_file($mecJson)) {
    Cli::stdOut('Finish' . PHP_EOL);
    exit(0);
}
$json = json_decode(file_get_contents($mecJson), true);
$insatllationCallbacks = $json['run-installer'] ?? [];

foreach ($insatllationCallbacks as $insatllationCallback) {
    if (str_starts_with($insatllationCallback, '@phar-cmd')) {
        $insatllationCallback = MDS_EASY_COMPLETION_PHAR_TARGET . substr($insatllationCallback, 9);
        $fp = popen($insatllationCallback, 'r');
        while (!feof($fp)) {
            echo fread($fp, 1024);
        }
        pclose($fp);
    }
}
Cli::stdOut('Finish' . PHP_EOL);
