<?php

use Medusa\EasyCompletion\Build\TempDir;
use Medusa\EasyCompletion\EasyCompletion;
use Medusa\EasyCompletion\Installer\Directory;

define('MDS_EASY_COMPLETION_INSTALL_MODE', __DIR__ . '/{{ BUILD_DIR }}/completion.phar');

$buildDir = __DIR__ . '/{{ BUILD_DIR }}';

function copyRecursive($dir) {

    $files = [];

    foreach (new DirectoryIterator($dir) as $item) {

        if ($item->isDot()) {
            continue;
        }

        if ($item->isFile()) {
            $files[] = $dir . '/' . $item->getFilename();
        } else {
            $files = array_merge(copyRecursive($dir . '/' . $item->getFilename()), $files);
        }
    }
    return $files;
}


//copy(__DIR__ . '/{{ BUILD_DIR }}/completion.phar', '{{ PHAR_TARGET }}.phar');
require_once __DIR__ .'/{{ BUILD_DIR }}/{{ ENTRYPOINT }}';
die;
foreach ($files as $file) {
    $source = __DIR__ . '/{{ BUILD_DIR }}/' . $file;
    $target = $targetRaw->getBuildDir() . '/' . $file;
    $targetDir = dirname($target);
    Directory::ensureExists($targetDir);
    Directory::ensureWriteable($targetDir);

}

Phar::loadPhar(__DIR__ . '/{{ BUILD_DIR }}/completion.phar', 'completion.phar');
require_once 'phar://completion.phar/{{ ENTRYPOINT }}';


die;
Phar::loadPhar('/path/to/phar.phar', 'my.phar');
echo file_get_contents('phar://my.phar/file.txt');

Phar::loadPhar();

#$phar = new \Phar();


die;
require_once __DIR__ . '/{{ BUILD_DIR }}/vendor/autoload.php';



die;
$targetRaw = new TempDir(getcwd());

$len = strlen($buildDir) + 1;
$files = array_map(fn($file) => substr($file, $len), copyRecursive($buildDir));

foreach ($files as $file) {
    $source = __DIR__ . '/{{ BUILD_DIR }}/' . $file;
    $target = $targetRaw->getBuildDir() . '/' . $file;
    $targetDir = dirname($target);
    Directory::ensureExists($targetDir);
    Directory::ensureWriteable($targetDir);

    copy($source, $target);
}

$exec = EasyCompletion::DEFAULT_PHP_INTERPRETER . ' ' . $targetRaw->getBuildDir() . '/{{ ENTRYPOINT }} --install';
$fp = popen($exec, 'r');
while (!feof($fp)) {
    echo fread($fp, 1024);
}
$targetRaw->destroy();
exit(pclose($fp));
