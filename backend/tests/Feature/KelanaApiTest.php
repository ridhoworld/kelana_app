<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelanaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_register_route_does_not_exist(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'secret123',
        ])->assertStatus(404);
    }

    public function test_admin_seeder_login_and_user_management_flow(): void
    {
        $this->seed(AdminUserSeeder::class);

        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => env('ADMIN_EMAIL', 'admin@kelana.app'),
            'password' => env('ADMIN_PASSWORD', 'password123'),
            'device_name' => 'pixel-test',
        ])->assertOk();

        $adminToken = $loginRes->json('data.token');
        $this->assertEquals('admin', $loginRes->json('data.user.role'));

        // Admin creates a regular user
        $createUserRes = $this->withToken($adminToken)->postJson('/api/v1/admin/users', [
            'name' => 'Budi Traveler',
            'email' => 'budi@kelana.app',
            'password' => 'rahasia123',
            'role' => 'user',
        ])->assertCreated();

        $budiId = $createUserRes->json('data.id');

        // Budi logs in
        $budiLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@kelana.app',
            'password' => 'rahasia123',
        ])->assertOk();
        $budiToken = $budiLogin->json('data.token');

        // Budi cannot access admin endpoints
        $this->app['auth']->forgetGuards();
        $this->withToken($budiToken)
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();

        // Admin cannot deactivate self
        $this->app['auth']->forgetGuards();
        $adminId = $loginRes->json('data.user.id');
        $this->withToken($adminToken)
            ->patchJson("/api/v1/admin/users/{$adminId}/toggle-active")
            ->assertStatus(422);

        // Admin deactivates Budi -> Budi cannot login
        $this->withToken($adminToken)
            ->patchJson("/api/v1/admin/users/{$budiId}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@kelana.app',
            'password' => 'rahasia123',
        ])->assertForbidden();
    }

    public function test_full_wishlist_to_trip_itinerary_budget_packing_flow(): void
    {
        $user = User::create([
            'name' => 'Siti',
            'email' => 'siti@kelana.app',
            'password' => 'password123',
            'role' => 'user',
            'is_active' => true,
        ]);

        $otherUser = User::create([
            'name' => 'Andi',
            'email' => 'andi@kelana.app',
            'password' => 'password123',
            'role' => 'user',
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $otherToken = $otherUser->createToken('test')->plainTextToken;

        // 1. Add destinations to wishlist
        $yogyaRes = $this->withToken($token)->postJson('/api/v1/destinations', [
            'name' => 'Candi Borobudur',
            'city' => 'Yogyakarta',
            'country' => 'Indonesia',
            'country_code' => 'ID',
            'latitude' => -7.607874,
            'longitude' => 110.203751,
            'category' => 'sejarah',
            'priority' => 5,
            'difficulty' => 'mudah',
            'cost_min' => 1500000,
            'cost_max' => 3000000,
            'best_months' => [5, 6, 7, 8],
            'tags' => ['sunrise', 'budaya'],
            'links' => [
                ['url' => 'https://example.com/borobudur', 'label' => 'Panduan Sunrise', 'link_type' => 'artikel'],
            ],
        ])->assertCreated();

        $yogyaId = $yogyaRes->json('data.id');

        $baliRes = $this->withToken($token)->postJson('/api/v1/destinations', [
            'name' => 'Ubud',
            'city' => 'Gianyar',
            'country' => 'Indonesia',
            'country_code' => 'ID',
            'latitude' => -8.506853,
            'longitude' => 115.262478,
            'category' => 'alam',
            'priority' => 4,
            'best_months' => [6, 7, 8, 9],
        ])->assertCreated();
        $baliId = $baliRes->json('data.id');

        // Isolation check: Andi cannot view Siti's destination
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)
            ->getJson("/api/v1/destinations/{$yogyaId}")
            ->assertForbidden();

        // Filter by category & month
        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/destinations?category=sejarah&month=6')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 2. Create multi-city trip (3 days: 2026-12-01 to 2026-12-03)
        $tripRes = $this->withToken($token)->postJson('/api/v1/trips', [
            'name' => 'Liburan Jawa-Bali',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'budget_amount' => 5000000,
            'budget_currency' => 'IDR',
            'destination_ids' => [$yogyaId, $baliId],
        ])->assertCreated();

        $tripId = $tripRes->json('data.id');
        $this->assertCount(3, $tripRes->json('data.days'));
        $this->assertCount(2, $tripRes->json('data.destinations'));

        // Wishlist status automatically updated to 'direncanakan'
        $this->withToken($token)
            ->getJson("/api/v1/destinations/{$yogyaId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'direncanakan');

        // 3. Add itinerary item on Day 1
        $day1Id = $tripRes->json('data.days.0.id');
        $itemRes = $this->withToken($token)->postJson("/api/v1/days/{$day1Id}/items", [
            'title' => 'Sunrise di Borobudur',
            'category' => 'aktivitas',
            'start_time' => '05:00',
            'end_time' => '08:00',
            'location' => 'Magelang',
            'estimated_cost' => 500000,
        ])->assertCreated();

        $itemId = $itemRes->json('data.id');

        // 4. Record real expense
        $this->withToken($token)->postJson("/api/v1/trips/{$tripId}/expenses", [
            'item_id' => $itemId,
            'description' => 'Tiket masuk & transport',
            'category' => 'aktivitas',
            'amount' => 450000,
        ])->assertCreated()
            ->assertJsonPath('summary.total_estimated', 500000)
            ->assertJsonPath('summary.total_spent', 450000)
            ->assertJsonPath('summary.remaining_budget', 4550000);

        // 5. Add & check packing item
        $packRes = $this->withToken($token)->postJson("/api/v1/trips/{$tripId}/packing-items", [
            'name' => 'Jaket tipis',
            'category' => 'pakaian',
            'quantity' => 2,
        ])->assertCreated();

        $packId = $packRes->json('data.id');
        $this->withToken($token)->putJson("/api/v1/packing-items/{$packId}", [
            'is_packed' => true,
        ])->assertOk()->assertJsonPath('data.is_packed', true);

        // 6. Trip summary & duplicate
        $this->withToken($token)->getJson("/api/v1/trips/{$tripId}/summary")
            ->assertOk()
            ->assertJsonPath('data.days_count', 3)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.budget.packing_packed', 1);

        $this->withToken($token)->postJson("/api/v1/trips/{$tripId}/duplicate", [
            'name' => 'Liburan Jawa-Bali 2027',
            'start_date' => '2027-06-01',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Liburan Jawa-Bali 2027');

        // 7. Mark destination as visited
        $this->withToken($token)->patchJson("/api/v1/destinations/{$yogyaId}/visit", [
            'visited_on' => '2026-12-01',
            'notes' => 'Sunrise sangat indah!',
        ])->assertOk()
            ->assertJsonPath('data.status', 'dikunjungi')
            ->assertJsonPath('data.visited_on', '2026-12-01');
    }
}
