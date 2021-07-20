<?php declare(strict_types = 1);
namespace Medusa\EasyCompletion\Build;

use ReflectionFunction;
use function array_filter;
use function file;
use function file_get_contents;
use function implode;
use function is_array;
use function token_get_all;
use function trim;
use const PHP_EOL;
use const T_FUNCTION;

/**
 * Class ExtractCallable
 * @package medusa/easy-completion
 * @author  Pascal Schnell <pascal.schnell@getmedusa.org>
 */
class ExtractCallable {

    public static function do(callable $exec): string {

        $t = new ReflectionFunction($exec);
        $raw = file_get_contents($t->getFileName());
        $content = file($t->getFileName());

        $exportStarted = false;
        $execExport = array_filter($content, function($row) use (&$exportStarted, &$exportStopped) {

            $row = trim($row);
            if ($row === '### STOP EXEC EXPORT ###') {
                $exportStarted = false;
            }
            if (!$exportStarted) {
                if ($row === '### START EXEC EXPORT ###') {
                    $exportStarted = true;
                }
                return false;
            }
            return true;
        });

        $execExport = trim(implode('', $execExport));
        $extractedClosure = '';
        $extractionStart = false;
        $bracketCount = null;

        foreach (token_get_all($raw) as $row) {

            if (!is_array($row)) {
                if ($extractionStart) {
                    if ($row === '{') {
                        $bracketCount ??= 0;
                        $bracketCount++;
                    } elseif ($row === '}') {
                        $bracketCount ??= 0;
                        $bracketCount--;
                    }

                    $extractedClosure .= $row;

                    if ($bracketCount === 0) {
                        break;
                    }
                }
                continue;
            }

            if ($row[2] < $t->getStartLine()) {
                continue;
            }

            if ($row[2] > $t->getEndLine()) {
                break;
            }

            if (!$extractionStart) {
                if ($row[0] !== T_FUNCTION) {
                    continue;
                }
            }

            $extractedClosure .= $row[1];
            $extractionStart = true;
        }

        $defaults = file_get_contents(__DIR__ . '/DefaultExecFunctions.php');
        $extractedClosure = $defaults
            . PHP_EOL
            . $execExport
            . PHP_EOL
            . '(' . $extractedClosure . ')();';

        return $extractedClosure;
    }
}