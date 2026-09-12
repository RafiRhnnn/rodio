<?php

// Second-pass diagnostic. Answers what the first one could not: is a worker
// process actually alive, what does the failing row say verbatim, and are there
// any non-test (web/worker) log lines at all?

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
$report = $root.'/storage/diag2.txt';

file_put_contents($report, 'diag2 '.date('c')."\n");

$write = fn (string $line) => file_put_contents($report, $line."\n", FILE_APPEND);

// 1. Is any php process running artisan queue:work / serve / schedule:work?
$tasks = [];
exec('tasklist /FO CSV /NH 2>&1', $tasks);

$alive = array_values(array_filter($tasks, fn ($l) => stripos($l, 'php') !== false));
$write('php processes = '.(count($alive) ?: 'NONE')."\n  ".implode("\n  ", array_slice($alive, 0, 10)));

$wmic = [];
exec('wmic process where "name=\'php.exe\'" get commandline /FORMAT:LIST 2>&1', $wmic);
$write('command lines:');
foreach (array_slice(array_values(array_filter(array_map('trim', $wmic), fn ($l) => str_contains($l, 'artisan'))), 0, 10) as $cmd) {
    $write('  '.$cmd);
}

// 2. The failing row, verbatim.
$write('-- conversions, untruncated');

try {
    foreach (DB::table('audio_conversions')->orderByDesc('id')->limit(6)->get() as $row) {
        $write("#{$row->id} created={$row->created_at} status={$row->status} attempts={$row->attempts}"
            ." started=".($row->started_at ?? '-')." failed=".($row->failed_at ?? '-'));
        $write("   original_path=".($row->original_path ?? '-'));
        $write("   output_path=".($row->output_path ?? '-'));
        $write("   error=".($row->error_message === null ? 'NULL' : $row->error_message));
    }
} catch (Throwable $e) {
    $write('unreadable: '.$e->getMessage());
}

// 3. Queue state.
try {
    $jobs = DB::table('jobs')->get();
    $write('-- jobs = '.$jobs->count());
    foreach ($jobs as $job) {
        $write("   id={$job->id} queue={$job->queue} attempts={$job->attempts} available_at="
            .date('c', (int) $job->available_at));
    }
} catch (Throwable $e) {
    $write('jobs unreadable: '.$e->getMessage());
}

try {
    $failed = DB::table('failed_jobs')->orderByDesc('id')->limit(2)->get();
    $write('-- failed_jobs = '.$failed->count());
    foreach ($failed as $job) {
        $write('   '.substr(str_replace("\n", ' ', (string) $job->error), 0, 1200));
    }
} catch (Throwable $e) {
    $write('failed_jobs unreadable: '.$e->getMessage());
}

// 4. Non-test log lines: the web/worker context never appeared last time.
$log = $root.'/storage/logs/laravel.log';

if (is_file($log)) {
    $all = file($log);
    $real = array_values(array_filter($all, function ($l) {
        return str_contains($l, '] local.') || str_contains($l, '] production.');
    }));

    $write('-- non-testing log lines = '.count($real).' (of '.count($all).')');

    foreach (array_slice($real, -25) as $line) {
        $write('   '.substr(preg_replace('/\s+/', ' ', $line), 0, 500));
    }
} else {
    $write('no laravel.log');
}

// 5. Config the worker would have been started with.
$write('QUEUE_CONNECTION env='.var_export(env('QUEUE_CONNECTION'), true)
    .' config='.config('queue.default')
    .' | SESSION_LIFETIME='.config('session.lifetime'));
