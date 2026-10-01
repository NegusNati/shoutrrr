<?php

declare(strict_types=1);

use App\Enums\Platform;
use App\Models\ConnectedAccount;

test('an owner can enable and disable native tracking', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Bluesky]);

    $this->postJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")->assertOk();
    $this->assertDatabaseHas('connected_account_native_watches', ['connected_account_id' => $account->id]);

    $this->deleteJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")->assertOk();
    $this->assertDatabaseMissing('connected_account_native_watches', ['connected_account_id' => $account->id]);
});

test('tracking an unsupported platform is rejected', function () {
    [, $workspace] = ownerActingIn();
    $account = ConnectedAccount::factory()->linkedin()->create(['workspace_id' => $workspace->id]);

    $this->postJson("/api/v1/sync-pipelines/native-tracking/{$account->id}")->assertUnprocessable();
    $this->assertDatabaseMissing('connected_account_native_watches', ['connected_account_id' => $account->id]);
});

test('enabling is blocked at the cap when subscriptions are enabled', function () {
    config(['subscriptions.enabled' => true, 'subscriptions.max_native_tracked' => 1]);
    [, $workspace] = ownerActingIn();
    $workspace->forceFill(['is_initial' => false])->save();
    $workspace->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_'.fake()->uuid(), 'stripe_status' => 'active', 'stripe_price' => 'price_test', 'quantity' => 1]);
    $a1 = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Bluesky]);
    $a2 = ConnectedAccount::factory()->create(['workspace_id' => $workspace->id, 'platform' => Platform::Bluesky]);
    $this->postJson("/api/v1/sync-pipelines/native-tracking/{$a1->id}")->assertOk();

    $this->postJson("/api/v1/sync-pipelines/native-tracking/{$a2->id}")->assertUnprocessable();
});
