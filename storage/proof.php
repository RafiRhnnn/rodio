<?php

// Proof runner. Writes its own report because child-process stdout is not
// observable through this shell.

$root = dirname(__DIR__);
$report = $root.'/storage/proof.txt';

file_put_contents($report, 'start '.date('c')."\n");

foreach (['app/Support/GeneratedAudio.php', 'tests/Feature/AudioUploadTest.php'] as $relative) {
    $buffer = [];

    exec('php -l '.escapeshellarg($root.'/'.str_replace('/', '\\', $relative)).' 2>&1', $buffer, $status);

    file_put_contents($report, ($status === 0 ? 'ok   ' : 'FAIL ').$relative.': '.trim(implode(' ', $buffer))."\n", FILE_APPEND);
}

exec('php artisan test --filter="AudioUploadTest|WorkerEndToEndTest" 2>&1', $out, $exit);

file_put_contents($report, 'exit='.$exit."\n", FILE_APPEND);
file_put_contents($report, trim(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $out))))."\n", FILE_APPEND);
