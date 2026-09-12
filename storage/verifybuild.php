<?php
// Rebuild, then prove the fix lives in the bundle the browser loads.

$root = dirname(__DIR__);
$report = $root.'/storage/verifybuild.txt';
$src = (string) file_get_contents($root.'/resources/js/converter.js');

$lines = ['src has href assignment: '.(str_contains($src, "'result-download').href") || str_contains($src, 'result-download\').href') ? 'YES' : 'NO']
    , 'src clears stale href: '.(str_contains($src, "result-download').removeAttribute('href')") ? 'YES' : 'NO');

exec('npm run build 2>&1', $out, $status);
$lines[] = 'npm run build exit='.$status;

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true);
$entry = (string) ($manifest['resources/js/app.js']['file'] ?? '');
$lines[] = 'entry='.$entry;

$js = is_file($root.'/public/build/'.$entry) ? (string) file_get_contents($root.'/public/build/'.$entry) : '';
$lines[] = 'bundle bytes='.strlen($js);
$lines[] = 'bundle mentions result-download: '.(str_contains($js, 'result-download') ? 'YES' : 'NO');
$lines[] = 'bundle assigns href: '.(preg_match('/result-download.{0,40}\.href/', $js) ? 'YES' : 'NO');
$lines[] = 'bundle clears href: '.(preg_match('/result-download.{0,60}removeAttribute\(.href.\)/', $js) ? 'YES' : 'NO');
$lines[] = 'newest js asset mtimes: '.json_encode(array_map(
    fn ($f) => basename($f).' '.date('H:i:s', (int) filemtime($f)),
    glob($root.'/public/build/assets/app-*.js') ?: []
));

file_put_contents($report, implode("\n", $lines)."\n");
