<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_view_home(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
    }
}
