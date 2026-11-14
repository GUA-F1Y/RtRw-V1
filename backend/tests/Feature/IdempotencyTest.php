<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    public function test_request_with_idempotency_key_is_saved_and_replayed_on_repeat(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $idempotencyKey = 'test-idemp-key-12345';
        $payload = [
            'number' => 5,
            'name' => 'RT 05',
        ];

        // 1st request creates the RT
        $first = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/rws/{$rw->id}/rts", $payload);

        $first->assertStatus(201)
            ->assertJsonPath('data.number', 5);

        $createdRtId = $first->json('data.id');

        // 2nd identical request replays cached response without creating duplicate
        $second = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/rws/{$rw->id}/rts", $payload);

        $second->assertStatus(201)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $createdRtId);

        // Verify only 1 RT was created in database
        $this->assertDatabaseCount('rts', 1);
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $idempotencyKey = 'test-idemp-key-payload-conflict';

        // 1st request
        $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/rws/{$rw->id}/rts", [
                'number' => 10,
                'name' => 'RT 10',
            ])->assertStatus(201);

        // 2nd request with different body
        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/v1/rws/{$rw->id}/rts", [
                'number' => 11,
                'name' => 'RT 11',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }
}
