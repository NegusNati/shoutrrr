<?php

use App\Models\PostingSchedule;

test('returns an empty schedule shape when none configured', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->getJson('/api/v1/posting-schedule')
        ->assertOk()
        ->assertExactJson(['timezone' => null, 'canManage' => true, 'slots' => []]);
});

test('returns timezone and slots when a schedule exists', function () {
    [, $workspace, $token] = issuedKey();
    $schedule = PostingSchedule::factory()->for($workspace)->create(['timezone' => 'America/New_York']);
    $schedule->slots()->create(['weekday' => 1, 'hour' => 9, 'minute' => 30, 'position' => 0]);

    $this->withToken($token)->getJson('/api/v1/posting-schedule')
        ->assertOk()
        ->assertJsonPath('timezone', 'America/New_York')
        ->assertJsonPath('canManage', true)
        ->assertJsonPath('slots.0.weekday', 1)
        ->assertJsonPath('slots.0.hour', 9)
        ->assertJsonPath('slots.0.minute', 30);
});

test('canManage is false for members without the settings permission', function () {
    [, $workspace, $token] = issuedKey();
    $workspace->members()->first()->update(['role' => 'member']);

    $this->withToken($token)->getJson('/api/v1/posting-schedule')
        ->assertOk()
        ->assertJsonPath('canManage', false);
});

test('update replaces the schedule slots and dedupes entries', function () {
    [, $workspace, $token] = issuedKey();
    PostingSchedule::factory()->for($workspace)->create(['timezone' => 'UTC'])
        ->slots()->create(['weekday' => 0, 'hour' => 8, 'minute' => 0, 'position' => 0]);

    $this->withToken($token)->putJson('/api/v1/posting-schedule', [
        'slots' => [
            ['weekday' => 1, 'hour' => 9, 'minute' => 30],
            ['weekday' => 1, 'hour' => 9, 'minute' => 30],
            ['weekday' => 3, 'hour' => 17],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'slots');

    $slots = PostingSchedule::query()->where('workspace_id', $workspace->id)->firstOrFail()->slots;
    expect($slots)->toHaveCount(2);
    expect($slots->pluck('position')->all())->toBe([0, 1]);
    expect($slots->firstWhere('weekday', 3)->minute)->toBe(0);
});

test('update validates the slot shape', function () {
    [, , $token] = issuedKey();

    $this->withToken($token)->putJson('/api/v1/posting-schedule', [
        'slots' => [['weekday' => 9, 'hour' => 25]],
    ])->assertStatus(422);
});

test('update 403s for members without the settings permission', function () {
    [, $workspace, $token] = issuedKey();
    $workspace->members()->first()->update(['role' => 'member']);

    $this->withToken($token)->putJson('/api/v1/posting-schedule', ['slots' => []])->assertForbidden();
});
