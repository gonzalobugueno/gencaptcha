<?php

function psr4($className) {
    $baseDir = __DIR__ . '/src/';
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    $file = $baseDir . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register('psr4');

$destdir = 'dataset';
$fmt = 'png';
$train = 0.7;
$val = 0.2;
$test = 0.1;
$w = 150;
$h = 40;

function help() {
    echo "Usage: php gen.php <length> [options]\n";
    echo "  <length>      Required: Number of samples (must be a positive number)\n";
    echo "  --destdir     Optional: The destination directory (default: 'dataset')\n";
    echo "  --fmt         Optional: Image format (default: 'png')\n";
    echo "  --train       Optional: The train split ratio (default: 0.7)\n";
    echo "  --val         Optional: The validation split ratio (default: 0.2)\n";
    echo "  --test        Optional: The test split ratio (default: 0.1)\n";
    echo "  -w            Optional: Image width (default: 150)\n";
    echo "  -h            Optional: Image height (default: 40)\n";
    exit(1);
}

if ($argc < 2) help();

$length = $argv[1];

if (!is_numeric($length) || $length <= 0) help();

for ($i = 2; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case '--destdir':
            $destdir = $argv[++$i] ?? $destdir;
            break;
        case '--fmt':
            $fmt = $argv[++$i] ?? $fmt;
            break;
        case '--train':
            $train = $argv[++$i] ?? (float)$train;
            break;
        case '--val':
            $val = $argv[++$i] ?? (float)$val;
            break;
        case '--test':
            $test = $argv[++$i] ?? (float)$test;
            break;
        case '-w':
            $w = $argv[++$i] ?? (int)$w;
            break;
        case '-h':
            $h = $argv[++$i] ?? (int)$h;
            break;
        default:
            echo "Unknown argument: {$argv[$i]}\n";
            exit(1);
    }
}


use Gregwar\Captcha\AnnotatedCaptchaBuilder;


$captcha = new AnnotatedCaptchaBuilder();

$captcha
    ->setIgnoreAllEffects(true);


for ($i = 0; $i < $length; ++$i) {
    $captcha
        ->build($w, $h)
        ->saveAsYoloFmt($destdir, $fmt, $train, $val, $test);
}

$captcha
    ->saveYaml($destdir);
