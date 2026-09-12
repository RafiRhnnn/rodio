<?php

// Are the completed rows real, playable audio? The browser tab cannot answer
// this, and it is the only question left.

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$report = dirname(__DIR__).'/storage/diag3.txt';
file_put_contents($report, 'diag3 '.date('c')."\n");

$write = fn (string $l) => file_put_contents($report, $l."\n", FILE_APPEND);
$disk = Storage::disk(config('audio.disk'));

foreach (DB::table('audio_conversions')->whereNotNull('output_path')->orderBy('id')->get() as $row) {
    $path = (string) $row->output_path;

    if (! $disk->exists($path)) {
        $write("#{$row->id} status={$row->status} MISSING {$path}");

        continue;
    }

    $full = $disk->path($path);
    $magic = file_get_contents($full, false, null, 0, 4);
    $seconds = (string) $row->original_duration;
    $expected = $seconds !== null && (float) $seconds > 0 ? round((float) $seconds / (float) $row->speed, 2) : null;

    $probe = [];
    exec('ffprobe -v error -show_entries format=duration,format_name -of default=nw=1 '.escapeshellarg($full).' 2>&1', $probe);

    $write(sprintf(
        "#%d %s | %s | %s b | magic=%s | db_dur=%ss expected=%ss | %s",
        $row->id,
        $row->status,
        $path,
        $disk->size($path),
        bin2hex($magic).' ('.trim($magic).')',
        $seconds ?: '-',
        $expected ?? '-',
        trim(str_replace("\n", ' ', implode(' ', $probe)))
    ));
}

$write('-- still waiting');

foreach (DB::table('audio_conversions')->whereIn('status', ['queued', 'processing'])->orderBy('id')->get() as $row) {
    $write("#{$row->id} {$row->status} attempts={$row->attempts} created={$row->created_at} started=".($row->started_at ?: '-'));
}
