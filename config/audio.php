<?php

use App\Enums\ConversionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;

return [

    'disk' => env('AUDIO_DISK', 'audio'),

    // Temporary uploads (private disk, never web-served).
    'upload_path' => 'uploads',

    // Converted files.
    'output_path' => 'converted',

    'max_upload_mb' => (float) env('AUDIO_MAX_UPLOAD_MB', 100),

    'max_upload_kb' => (int) ((float) env('AUDIO_MAX_UPLOAD_MB', 100) * 1024),

    /*
     | Supported input formats: required extension -> MIME types that content
     | sniffing (fileinfo) may report for it. The extension is only a claim.
     */
    'formats' => [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'audio/m4a'],
        'ogg' => ['audio/ogg', 'application/ogg', 'audio/vorbis', 'audio/opus'],
        'flac' => ['audio/flac', 'audio/x-flac'],
    ],

    // Allowed outputs and the encoder ffmpeg is called with for each of them.
    'outputs' => [
        'ogg' => ['codec' => 'libvorbis', 'bitrate' => '160k'],
    ],

    'default_output_format' => env('AUDIO_OUTPUT_FORMAT', 'ogg'),

    'speed' => [
        'default' => (float) env('AUDIO_SPEED_DEFAULT', 2.3),
        'min' => (float) env('AUDIO_SPEED_MIN', 0.5),
        'max' => (float) env('AUDIO_SPEED_MAX', 4.0),
        'presets' => [
            ['label' => 'Lambat', 'value' => 2.1],
            ['label' => 'Default', 'value' => 2.3],
            ['label' => 'Cepat', 'value' => 2.5],
            ['label' => 'Lebih Cepat', 'value' => 2.7],
            ['label' => 'Ultra Cepat', 'value' => 2.9],
        ],
    ],

    // Absolute binary paths: a queue worker does not inherit the shell PATH.
    'ffmpeg' => env('FFMPEG_PATH', 'C:/laragon/bin/ffmpeg/bin/ffmpeg.exe'),
    'ffprobe' => env('FFPROBE_PATH', 'C:/laragon/bin/ffmpeg/bin/ffprobe.exe'),

    'process_timeout' => (int) env('AUDIO_PROCESS_TIMEOUT', 600),

    'probe_timeout' => (int) env('AUDIO_PROBE_TIMEOUT', 30),

    // Percent. Progress writes are collapsed to this granularity so the status
    // endpoint is not hammered by FFmpeg's ~2 updates per second.
    'progress_step' => (int) env('AUDIO_PROGRESS_STEP', 5),

    'queue' => env('AUDIO_QUEUE', 'audio-conversions'),

    'queue_tries' => (int) env('AUDIO_QUEUE_TRIES', 3),

    'queue_timeout' => (int) env('AUDIO_QUEUE_TIMEOUT', 600),

    // How many audio-conversion workers may run at once on this machine.
    'max_workers' => (int) env('AUDIO_MAX_WORKERS', 2),

    /*
     | Retention: converted files are physical only for this many hours. After
     | that the object is deleted but the history row stays, and download
     | becomes "no longer available".
     */
    'retention_hours' => (int) env('AUDIO_RETENTION_HOURS', 24),

    // A conversion stuck in "processing" for longer than this is recovered.
    'stuck_after_minutes' => (int) env('AUDIO_STUCK_AFTER_MINUTES', 30),

    // Throttle "key:minutes-attempts" per bucket. Buckets are used in routes.
    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN', 10),
        'conversion' => (int) env('RATE_LIMIT_CONVERSION', 20),
        'status' => (int) env('RATE_LIMIT_STATUS', 240),
        'download' => (int) env('RATE_LIMIT_DOWNLOAD', 60),
    ],

    'roles' => array_column(UserRole::cases(), 'value'),
    'statuses' => array_column(UserStatus::cases(), 'value'),
    'conversion_statuses' => array_column(ConversionStatus::cases(), 'value'),
];
