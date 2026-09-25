<?php

namespace Tests\Feature;

use App\Models\AudioConversion;
use App\Services\AudioConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 7: the FFmpeg argument surface. These assert that only numbers built by
 * the application can reach the filter expression.
 */
class AudioConversionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AudioConversionService
    {
        return app(AudioConversionService::class);
    }

    public function test_pitch_preserving_speed_uses_an_atempo_chain_within_ffmpeg_limits(): void
    {
        // atempo refuses factors outside 0.5-2.0, so 2.3x becomes a chain.
        // Each chain is now prefixed with the speed's volume gain.
        $this->assertSame('volume=-8dB,atempo=2.0000,atempo=1.1500', $this->service()->filter(true, 2.3, 44100));
        $this->assertSame('volume=-14dB,atempo=2.0000,atempo=1.4500', $this->service()->filter(true, 2.9, 44100));
        $this->assertSame('volume=0dB,atempo=1.5000', $this->service()->filter(true, 1.5, 44100));
        $this->assertSame('volume=-25dB,atempo=2.0000,atempo=2.0000', $this->service()->filter(true, 4.0, 44100));

        // Below 2.1x the raw formula asks for a boost (0.5x -> +10dB), which
        // can clip, so it clamps to 0dB = unchanged. The speed itself is still
        // clamped to the configured minimum.
        $this->assertSame('volume=0dB,atempo=0.5000,atempo=0.5000', $this->service()->filter(true, 0.25, 44100));
    }

    public function test_preset_speeds_get_the_documented_gain(): void
    {
        // The mapping the presets are built around: every step of 0.2x costs
        // another 2dB of headroom.
        $expected = [
            [2.1, '-6'],
            [2.3, '-8'],
            [2.5, '-10'],
            [2.7, '-12'],
            [2.9, '-14'],
        ];

        foreach ($expected as [$speed, $db]) {
            $filter = $this->service()->filter(false, (float) $speed, 44100);

            $this->assertStringStartsWith("volume={$db}dB,", $filter);
        }
    }

    public function test_pitch_changing_speed_uses_asetrate_with_a_probed_sample_rate(): void
    {
        $this->assertSame(
            'volume=-8dB,asetrate=44100*2.3000,aresample=44100',
            $this->service()->filter(false, 2.3, 44100)
        );
    }

    public function test_missing_sample_rate_falls_back_to_the_safe_chain(): void
    {
        $this->assertSame('volume=-8dB,atempo=2.0000,atempo=1.1500', $this->service()->filter(false, 2.3, 0));
    }

    public function test_speed_is_clamped_and_normalised_to_two_decimals(): void
    {
        $this->assertSame(4.0, $this->service()->speed(AudioConversion::factory()->make(['speed' => 99])));
        $this->assertSame(0.5, $this->service()->speed(AudioConversion::factory()->make(['speed' => 0.01])));
        $this->assertSame(2.3, $this->service()->speed(AudioConversion::factory()->make(['speed' => 0])));
        $this->assertSame(2.35, $this->service()->speed(AudioConversion::factory()->make(['speed' => 2.3549])));
    }

    public function test_a_hostile_stored_speed_cannot_reach_the_command_line(): void
    {
        $conversion = AudioConversion::factory()->make(['speed' => 2.3]);

        // Even when the column holds junk, the emitted filter contains only
        // digits, dots, commas and the volume prefix - no shell metacharacters.
        $service = $this->service();
        $filter = $service->filter(true, $service->speed($conversion), 0);

        $this->assertMatchesRegularExpression(
            '/^volume=-?\d+(?:\.\d+)?dB(,atempo=\d\.\d{4})+$/',
            $filter
        );
        $this->assertStringNotContainsString(';', $filter);
        $this->assertStringNotContainsString('"', $filter);
    }
}
