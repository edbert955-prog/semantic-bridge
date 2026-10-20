<?php

use Symfony\Component\Process\Process;

require __DIR__ . '/vendor/autoload.php';

$java = 'C:/Program Files/Common Files/Oracle/Java/javapath/java.exe';

$jar = __DIR__
    . '/semantic-reasoner/target/semantic-owl2rl-reasoner.jar';

$ontologyDir = __DIR__ . '/storage/app/ontologies';

$inputFile = __DIR__
    . '/storage/app/semantic-graphs/924f2823-e1d8-4b38-ac9b-969d8341ed0d.source.ttl';

// Gunakan file output baru untuk pengujian.
$outputFile = __DIR__
    . '/storage/app/semantic-graphs/php-process-diagnostic.ttl';

$paths = [
    'Java' => $java,
    'JAR' => $jar,
    'Ontology directory' => $ontologyDir,
    'Input RDF' => $inputFile,
];

foreach ($paths as $label => $path) {
    $exists = $label === 'Ontology directory'
        ? is_dir($path)
        : is_file($path);

    echo $label . ': '
        . ($exists ? 'OK' : 'TIDAK DITEMUKAN')
        . PHP_EOL;
}

if (
    !is_file($java) ||
    !is_file($jar) ||
    !is_dir($ontologyDir) ||
    !is_file($inputFile)
) {
    exit(2);
}

try {
    $process = new Process(
        [
            $java,
            '-jar',
            $jar,
            $ontologyDir,
            $inputFile,
            $outputFile,
        ],
        __DIR__
    );

    $process->setTimeout(90);
    $process->run();

    echo PHP_EOL . 'EXIT CODE: '
        . var_export($process->getExitCode(), true)
        . PHP_EOL;

    echo 'STDOUT:' . PHP_EOL;
    echo $process->getOutput() . PHP_EOL;

    echo 'STDERR:' . PHP_EOL;
    echo $process->getErrorOutput() . PHP_EOL;

    echo 'OUTPUT EXISTS: '
        . (is_file($outputFile) ? 'True' : 'False')
        . PHP_EOL;
} catch (Throwable $e) {
    echo 'PHP EXCEPTION: '
        . get_class($e)
        . PHP_EOL;

    echo $e->getMessage() . PHP_EOL;

    exit(1);
}