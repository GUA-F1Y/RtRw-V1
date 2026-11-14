<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authorization\Enums\RoleKey;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Rw;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageTest extends TestCase
{
    public function test_authorized_user_can_upload_private_file(): void
    {
        Storage::fake('private');

        $organization = Organization::factory()->create();
        $rw = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);

        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw);

        $fakeFile = UploadedFile::fake()->create('governance_charter.pdf', 200, 'application/pdf');

        $response = $this->postJson('/api/v1/files', [
            'file' => $fakeFile,
            'scope_type' => 'rw',
            'scope_id' => $rw->id,
            'category' => 'governance',
            'visibility' => 'private',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.original_name', 'governance_charter')
            ->assertJsonPath('data.extension', 'pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.visibility', 'private')
            ->assertJsonPath('data.rw_id', $rw->id);

        $this->assertDatabaseHas('files', [
            'original_name' => 'governance_charter',
            'extension' => 'pdf',
            'rw_id' => $rw->id,
            'visibility' => 'private',
        ]);
    }

    public function test_unauthorized_user_outside_scope_cannot_upload_file(): void
    {
        Storage::fake('private');

        $organization = Organization::factory()->create();
        $rw1 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);
        $rw2 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 2]);

        // Authenticate as Ketua RW 01
        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw1);

        $fakeFile = UploadedFile::fake()->create('unauthorized.pdf', 100, 'application/pdf');

        // Attempting to upload to RW 02
        $response = $this->postJson('/api/v1/files', [
            'file' => $fakeFile,
            'scope_type' => 'rw',
            'scope_id' => $rw2->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_private_file_download_is_forbidden_for_user_outside_scope(): void
    {
        Storage::fake('private');

        $organization = Organization::factory()->create();
        $rw1 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 1]);
        $rw2 = Rw::factory()->create(['organization_id' => $organization->id, 'number' => 2]);

        // Upload file as Ketua RW 01
        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw1);
        $file = UploadedFile::fake()->create('private_record.pdf', 100, 'application/pdf');
        $upload = $this->postJson('/api/v1/files', [
            'file' => $file,
            'scope_type' => 'rw',
            'scope_id' => $rw1->id,
            'visibility' => 'private',
        ]);
        $fileId = $upload->json('data.id');

        // Switch to Ketua RW 02
        $this->actAsScopedUser(RoleKey::KetuaRw, $organization, $rw2);

        $downloadResponse = $this->getJson("/api/v1/files/{$fileId}/download");
        $downloadResponse->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
}
