<?php

// Failure diagnostic: the browser only shows a generic message, so the truth
// lives in the conversion row, the failed_jobs table and the log.

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
$report = $root.'/storage/diag.txt';

file_put_contents($report, 'diag '.date('c')."\n");

$write = fn (string $line) => file_put_contents($report, $line."\n", FILE_APPEND);

// Is FFmpeg actually where config says it is?
foreach (['ffmpeg', 'ffprobe'] as $binary) {
    $path = (string) config("audio.$binary");

    $write("$binary: $path => ".(is_file($path) ? 'FOUND' : 'MISSING'));
}

$write('queue connection = '.config('queue.default').' | queue name = '.config('audio.queue')
    .' | max_workers = '.config('audio.max_workers'));

$write('-- last 8 conversions');

try {
    foreach (DB::table('audio_conversions')->orderByDesc('id')->limit(8)->get() as $row) {
        $write(sprintf(
            '#%d %s fmt=%s out=%s status=%s progress=%s attempts=%s speed=%s path=%s err=%s',
            $row->id,
            (string) $row->created_at,
            (string) $row->original_format,
            (string) $row->output_format,
            (string) $row->status,
            (string) $row->progress,
            (string) ($row->attempts ?? '-'),
            (string) $row->speed,
            (string) ($row->output_path ?: '-'),
            trim(preg_replace('/\s+/', ' ', (string) ($row->error_message ?: '-')))
        ));
    }
} catch (Throwable $e) {
    $write('audio_conversions unreadable: '.$e->getMessage());
}

$write('-- failed_jobs (last 3 payload excerpts)');

try {
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get() as $job) {
        $payload = (string) $job->payload;
        $write('connection='.$job->connection.' queue='.($job->queue ?? '-'));
        $write('  error: '.trim(preg_replace('/\s+/', ' ', substr((string) $job->error, 0, 700))));
        $write('  payload: '.substr(preg_replace('/\s+/', ' ', $payload), 0, 300));
    }
} catch (Throwable $e) {
    $write('failed_jobs unreadable: '.$e->getMessage());
}

$write('-- pending jobs');

try {
    $write('jobs table rows = '.DB::table('jobs')->count());
} catch (Throwable $e) {
    $write('jobs table unreadable: '.$e->getMessage());
}

$write('-- uploads on disk');

try {
    $disk = Illuminate\Support\Facades\Storage::disk(config('audio.disk'));
    $write('uploads: '.json_encode(array_map(
        fn ($p) => $p.' ('.$disk->size($p).' b)',
        $disk->files((string) config('audio.upload_path'))
    )));
    $write('converted: '.json_encode($disk->files((string) config('audio.output_path'))));
} catch (Throwable $e) {
    $write('disk unreadable: '.$e->getMessage());
}

$write('-- laravel.log tail');

$log = $root.'/storage/logs/laravel.log';

if (is_file($log)) {
    $lines = file($log);
    $tail = array_slice($lines, -60);
    $write('log lines total = '.count($lines));
    $write(implode('', array_map(
        fn ($l) => substr(preg_replace('/\s+/', ' ', $l), 0, 400),
        $tail
    )));
} else {
    $write('no laravel.log');
}
