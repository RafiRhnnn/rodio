<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\File\File;

/**
 * A real, decodable WAV written to the system temp directory.
 *
 * UploadedFile::fake() cannot stand in for an FFmpeg input: it writes random
 * bytes behind a plausible MIME type. That difference is exactly how
 * "Format audio tidak didukung." survived a green suite while rejecting real
 * audio. These are actual PCM samples, so fileinfo, ffprobe and the converter
 * all see genuine sound.
 */
class GeneratedAudio extends File
{
    /** Absolute path to the generated file. */
    public string $path;

    /** Byte size of the written file. */
    public int $size;

    /**
     * @param  int  $frames  one frame per sample (16-bit mono)
     */
    public function __construct(
        public int $frames = 35_000,
        public int $sampleRate = 44_100,
        public int $frequency = 440,
        public int $channels = 1,
    ) {
        $frames = max(1, $frames);
        $bitsPerSample = 16;
        $blockAlign = $this->channels * ($bitsPerSample / 8);
        $byteRate = $this->sampleRate * $blockAlign;
        $dataSize = $frames * $blockAlign;

        $samples = '';

        for ($frame = 0; $frame < $frames; $frame++) {
            $sample = (int) round(12_000 * sin(2 * M_PI * $this->frequency * $frame / $this->sampleRate));

            for ($channel = 0; $channel < $this->channels; $channel++) {
                $samples .= pack('v', $sample);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'generated-audio-').'.wav';

        file_put_contents($path, implode('', [
            'RIFF',
            pack('V', 36 + $dataSize),
            'WAVE',
            'fmt ',
            pack('V', 16),
            pack('v', 1),                  // PCM
            pack('v', $this->channels),
            pack('V', $this->sampleRate),
            pack('V', $byteRate),
            pack('v', $blockAlign),
            pack('v', $bitsPerSample),
            'data',
            pack('V', $dataSize),
            $samples,
        ]));

        $this->path = $path;
        $this->size = (int) filesize($path);

        parent::__construct($path, true);
    }

    /** Seconds of audio the fixture holds. */
    public function seconds(): float
    {
        return $this->frames / $this->sampleRate;
    }

    public function duration(): float
    {
        return $this->seconds();
    }

    public function bytes(): int
    {
        return $this->size;
    }

    public function __destruct()
    {
        if (($this->path ?? '') !== '' && is_file($this->path)) {
            @unlink($this->path);
        }
    }
}

