<?php

/**
 * Copyright (c) 2019 Potherca
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Potherca\CLI;

use Potherca\Complexity\CognitiveComplexityScanner;
use Potherca\Complexity\CyclomaticComplexityScanner;
use PHP_CodeSniffer\Tokenizers\PHP as Tokenizer;
use Symplify\CodingStandard\TokenRunner\Analyzer\SnifferAnalyzer\CognitiveComplexityAnalyzer;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/squizlabs/php_codesniffer/autoload.php';
require_once __DIR__.'/../vendor/squizlabs/php_codesniffer/src/Util/Tokens.php';

if (defined('PHP_CODESNIFFER_VERBOSITY') === false) {
    define('PHP_CODESNIFFER_VERBOSITY', false);
}

function findArgvOption($array, $entry) {
    $index = array_search($entry, $array);
    if ($index !== false) {
        $result = array_slice($array, $index, 2);
        return $result;
    }
    return false;
}

function formatPretty($data, &$output) {
    array_walk($data, function ($data) use (&$output) {
        $scores = array_map(function ($cyclomatic, $cognitive) {
            return vsprintf('%d = cy(%d) * co(%d)', [$cognitive * $cyclomatic, $cognitive, $cyclomatic]);
        }, $data['cyclomatic'], $data['cognitive']);

        $output .= "\n\n\tFile : {$data['file']}";
        $methods = join("\n\t\t  ", $scores);
        $output .= "\n\tMethods : {$methods}";
    });
}

function formatCsv($data, &$output) {
    $output .= "File,Complexity,Cognitive,Cyclomatic\n";
    array_walk($data, function ($data) use (&$output) {
        $scores = array_map(function ($cyclomatic, $cognitive) {
            return vsprintf('%d,%d,%d', [$cognitive * $cyclomatic, $cognitive, $cyclomatic]);
        }, $data['cyclomatic'], $data['cognitive']);       

        foreach ($scores as $score) {
            $output .= vsprintf("%s,%s\n", [
                $data['file'],
                $score
            ]);
        }

        // $scores = array_map(function ($cyclomatic, $cognitive) {
        //     return vsprintf('%d = cy(%d) * co(%d)', [$cognitive * $cyclomatic, $cognitive, $cyclomatic]);
        // }, $data['cyclomatic'], $data['cognitive']);

        // $output .= "\n\n\tFile : {$data['file']}";
        // $methods = join("\n\t\t  ", $scores);
        // $output .= "\n\tMethods : {$methods}";
    });
}

// Remove the script name
array_shift($argv);

// Pop named arguments
$format = findArgvOption($argv, '--format');

/*/ Only use files that exist /*/
$files = array_filter($argv, function ($file) {
   return is_file($file);
});

$files = array_unique($files);

/*/ Get file(s) contents /*/
$contents = [];
array_walk($files, function ($file) use (&$contents){
    $contents[$file] = file_get_contents($file);
});

// $config = new Config(['dummy'], false);

/*/ Create Scanner /*/
$config = new \stdClass();
$config->tabWidth = 4;
$config->annotations = false;
$config->encoding = 'UTF-8';

$cognitive = [];
/*/ Calculate Cognitive Complexity /*/
array_walk($contents, function ($fileContent, $file) use (&$cognitive, $config) {
    $analyzer = new CognitiveComplexityAnalyzer();
    $tokenizer = new Tokenizer($fileContent, $config, PHP_EOL);
    $scanner = new CognitiveComplexityScanner($tokenizer, $analyzer);

    $cognitive[$file] = $scanner->calculate();
});

/*/ Calculate Cyclomatic Complexity /*/
$cyclomatic = [];
array_walk($contents, function ($fileContent, $file) use (&$cyclomatic, $config) {
    $tokenizer = new Tokenizer($fileContent, $config, PHP_EOL);
    $scanner = new CyclomaticComplexityScanner($tokenizer);

    $cyclomatic[$file] = $scanner->calculate();
});

/*/ Combine data sets /*/
$data = array_map(function ($file, $cyclomatic, $cognitive) {
    return [
        'cognitive' => $cognitive,
        'cyclomatic' => $cyclomatic,
        'file' => $file,
    ];
}, $files, $cyclomatic, $cognitive);

/*/ Build Output /*/
$output = '';
switch ($format[1]) {
    default:
    case 'pretty':
        formatPretty($data, $output);
        break;
    case 'csv':
        formatCsv($data, $output);
        break;
}

echo $output;

exit;
