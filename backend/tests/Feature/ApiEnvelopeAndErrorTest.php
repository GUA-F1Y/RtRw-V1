<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Tests\TestCase;

class ApiEnvelopeAndErrorTest extends TestCase
{
    public function test_validation_errors_produce_standard_error_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            // missing password
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'request_id',
                    'errors' => ['email', 'password'],
                ],
            ]);
    }

    public function test_custom_request_id_is_propagated_in_header_and_body(): void
    {
        $customRequestId = 'client-req-12345678';

        $response = $this->withHeader('X-Request-Id', $customRequestId)
            ->postJson('/api/v1/auth/login', []);

        $response->assertHeader('X-Request-Id', $customRequestId)
            ->assertJsonPath('error.request_id', $customRequestId);
    }

    public function test_resource_not_found_returns_standard_404_envelope(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $fakeUuid = '01924b12-0000-7000-8000-000000000000';
        $response = $this->getJson("/api/v1/rws/{$fakeUuid}");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_successful_list_response_adheres_to_envelope(): void
    {
        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $response = $this->getJson('/api/v1/organizations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'has_more',
                    ],
                    'request_id',
                ],
            ]);
    }
}
