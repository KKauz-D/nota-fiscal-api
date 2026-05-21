<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_update_im_without_authentication(): void
    {
        $response = $this->putJson('/api/empresas/12345678901234', [
            'im' => '999999',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_im_validation_requires_im(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/empresas/12345678901234', [
            'im' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['im']);
    }

    public function test_update_im_returns_404_if_company_does_not_exist(): void
    {
        Storage::fake('certificates');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/empresas/12345678901234', [
            'im' => '123456',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Empresa não encontrada.',
            ]);
    }

    public function test_update_im_updates_config_json_file_successfully(): void
    {
        Storage::fake('certificates');

        // Setup a fake certificate JSON configuration
        $cnpj = '12345678901234';
        $config = [
            'cert_file' => 'cert_12345678901234.pfx',
            'cert_password' => 'encryptedpassword',
            'im' => '111111',
            'saved_at' => '2026-05-21 00:00:00',
        ];
        Storage::disk('certificates')->put("{$cnpj}.json", json_encode($config));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/empresas/{$cnpj}", [
            'im' => '222222',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inscrição municipal atualizada com sucesso.',
                'data' => [
                    'cnpj' => $cnpj,
                    'im' => '222222',
                ]
            ]);

        // Assert JSON file in fake storage was updated
        $updatedConfig = json_decode(Storage::disk('certificates')->get("{$cnpj}.json"), true);
        $this->assertEquals('222222', $updatedConfig['im']);
    }
}
