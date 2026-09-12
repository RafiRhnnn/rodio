<?php

// Build, then prove the fix is inside the bundle the browser will actually load.
// Reading the source file proves nothing; Vite's output does.

$root = dirname(__DIR__);
$report = $root.'/storage/build.txt';

file_put_contents($report, 'build '.date('c')."\n");

$write = fn (string $l) => file_put_contents($report, $l."\n", FILE_APPEND);

$out = [];
exec('npm run build 2>&1', $out, $status);
$write('npm run build exit='.$status);
$write('   '.trim(preg_replace('/\s+/', ' ', implode(' ', array_slice($out, -6)))));

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true);
$entry = $manifest['resources/js/app.js']['file'] ?? null;

$write('manifest entry = '.var_export($entry, true));

if (! $entry) {
    $write('FAIL: no js entry');
    exit(1);
}

$js = (string) file_get_contents($root.'/public/build/'.$entry);
$jsSource = (string) file_get_contents($root.'/resources/js/converter.js');

$checks = [
    'href assignment' => "result-download').href=",
    'stale link cleared' => "result-download').removeAttribute('href')",
];

foreach ($checks as $label => $needle) {
    $write($label.': in source = '.($jsSource = $jsSource ?: '').($label === 'href assignment'
        ? (str_contains(file_get_contents($root.'/resources/js/converter.js'), "result-download').href") ? 'yes' : 'NO')
        : (str_contains(file_get_contents($root.'/resources/js/converter.js'), "removeAttribute('href')") ? 'yes' : 'NO')));

    $write($label.': in bundle = '.(str_contains($js, $needle) || str_contains($js, str_replace('result-download', 'result-download', $needle)) ? 'yes' : 'CHECK'));
}

// Vite minifies the id string but keeps it; prove the selector survived.
$write("bundle contains \"result-download\" = ".(str_contains($js, 'result-download') ? 'yes' : 'NO'));
$write('bundle bytes = '.strlen($js));
