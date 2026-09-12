<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tahap 12: history for the owner and for the admin, with bounded queries
 * (pagination + eager loading) instead of loading every row.
 */
class ConversionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    public function test_a_user_sees_only_their_own_history_paginated(): void
    {
        $user = User::factory()->create();
        AudioConversion::factory()->for($user)->count(12)->create();
        $foreign = AudioConversion::factory()->create();

        $page = $this->actingAs($user)->get('/history')->assertOk();
        $conversions = $page->viewData('conversions');

        $this->assertCount(10, $conversions);
        $this->assertSame(12, $conversions->total());
        $this->assertNotContains($foreign->id, $conversions->pluck('id')->all());
    }

    public function test_history_lists_every_required_column_with_both_durations(): void
    {
        $user = User::factory()->create();
        AudioConversion::factory()->for($user)->completed()->create([
            'original_filename' => 'lagu-saya.mp3',
            'original_size' => 3 * 1048576,
            'original_duration' => 600,
            'output_duration' => 261,
            'speed' => 2.3,
        ]);

        $this->actingAs($user)->get('/history')
            ->assertSee('lagu-saya.mp3')
            ->assertSee('2,30')
            ->assertSee('3.0 MB')
            ->assertSee('10:00')
            ->assertSee('04:21')
            ->assertSee('Selesai');
    }

    public function test_history_does_not_issue_one_query_per_row(): void
    {
        $user = User::factory()->create();
        AudioConversion::factory()->for($user)->count(10)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/history')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Session/auth + a count + the page query: not one more per row.
        $this->assertLessThan(12, $queries, 'History appears to query per row.');
    }

    public function test_admin_sees_every_conversion_with_its_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create(['name' => 'Alice Kunci']);
        $bob = User::factory()->create(['name' => 'Bob Sandi']);

        AudioConversion::factory()->for($alice)->create();
        AudioConversion::factory()->for($bob)->create();

        $page = $this->actingAs($admin)->get('/admin/conversions')->assertOk();

        $this->assertSame(2, $page->viewData('conversions')->total());
        $page->assertSee('Alice Kunci')->assertSee('Bob Sandi');
    }

    public function test_admin_history_loads_owners_without_a_query_per_row(): void
    {
        User::factory()->admin()->create();
        foreach (range(1, 8) as $ignored) {
            AudioConversion::factory()->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/conversions')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(14, $queries, 'Admin history loads the user per row.');
    }

    public function test_admin_history_filters_by_user_status_speed_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $alice = User::factory()->create(['name' => 'Alice']);

        AudioConversion::factory()->for($alice)->create(['speed' => 2.9, 'status' => ConversionStatus::Completed]);
        AudioConversion::factory()->create(['speed' => 1.5, 'status' => ConversionStatus::Failed]);
        AudioConversion::factory()->create(['speed' => 2.9, 'created_at' => now()->subYears(2)]);

        $count = fn (string $query) => $this->actingAs($admin)
            ->get('/admin/conversions?'.$query)->assertOk()->viewData('conversions')->total();

        $this->assertSame(1, $count('user=Alice'));
        $this->assertSame(1, $count('status=completed'));
        $this->assertSame(2, $count('speed=2.9'));
        $this->assertSame(2, $count('date_from='.now()->subDay()->toDateString()));
    }

    public function test_invalid_filters_are_rejected_instead_of_loosening_the_query(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/conversions?status=bukan-status')->assertSessionHasErrors('status');
        $this->actingAs($admin)->get('/admin/conversions?speed=abc')->assertSessionHasErrors('speed');
        $this->actingAs($admin)
            ->get('/admin/conversions?date_from=2026-05-05&date_to=2026-01-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_admin_can_retry_a_failed_conversion(): void
    {
        $admin = User::factory()->admin()->create();
        $conversion = AudioConversion::factory()->failed()->create(['attempts' => 3]);

        $this->actingAs($admin)->post('/admin/conversions/'.$conversion->id.'/retry')->assertRedirect();

        $this->assertSame(ConversionStatus::Queued, $conversion->refresh()->status);
        $this->assertSame(3, $conversion->attempts, 'Retry must not erase how often it was tried.');
        Queue::assertPushed(ProcessAudioConversion::class);
    }

    public function test_a_completed_conversion_cannot_be_retried(): void
    {
        $admin = User::factory()->admin()->create();
        $conversion = AudioConversion::factory()->completed()->create();

        $this->actingAs($admin)->post('/admin/conversions/'.$conversion->id.'/retry')
            ->assertSessionHasErrors('conversion');

        $this->assertSame(ConversionStatus::Completed, $conversion->refresh()->status);
    }

    public function test_a_user_cannot_open_or_retry_admin_history(): void
    {
        $user = User::factory()->create();
        $conversion = AudioConversion::factory()->for($user)->failed()->create();

        $this->actingAs($user)->get('/admin/conversions')->assertForbidden();
        $this->actingAs($user)->post('/admin/conversions/'.$conversion->id.'/retry')->assertForbidden();

        $this->assertSame(ConversionStatus::Failed, $conversion->refresh()->status);
    }
}
