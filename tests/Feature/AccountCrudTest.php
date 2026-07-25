<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_can_be_retrieved(): void
    {
        [$user, $campaign] = $this->superAdminWithCampaign();
        $account = Account::query()->create([
            'campaign_id' => $campaign->id,
            'account_number' => 'ACC-RETRIEVE-001',
            'account_name' => 'Retrieve Account',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Show')
                ->where('account.id', $account->id)
                ->where('account.account_name', 'Retrieve Account')
            );
    }

    public function test_account_can_be_created(): void
    {
        [$user, $campaign] = $this->superAdminWithCampaign();

        $response = $this->actingAs($user)
            ->post(route('accounts.store'), [
                'campaign_id' => $campaign->id,
                'account_name' => 'Created Account',
                'account_number' => 'ACC-CREATE-001',
            ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('accounts', [
            'campaign_id' => $campaign->id,
            'account_number' => 'ACC-CREATE-001',
            'account_name' => 'Created Account',
        ]);
    }

    public function test_account_can_be_updated(): void
    {
        [$user, $campaign] = $this->superAdminWithCampaign();
        $account = Account::query()->create([
            'campaign_id' => $campaign->id,
            'account_number' => 'ACC-UPDATE-001',
            'account_name' => 'Before Update',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->put(route('accounts.update', $account), [
                'campaign_id' => $campaign->id,
                'account_number' => 'ACC-UPDATE-001',
                'account_name' => 'After Update',
            ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'account_name' => 'After Update',
        ]);
    }

    public function test_accounts_index_does_not_expose_balance(): void
    {
        [$user, $campaign] = $this->superAdminWithCampaign();
        Account::query()->create([
            'campaign_id' => $campaign->id,
            'account_number' => 'ACC-LIST-001',
            'account_name' => 'Listed Account',
            'balance' => 12345.67,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Index')
                ->has('accounts.data', 1)
                ->has('accounts.data.0', fn (Assert $row) => $row
                    ->where('account_number', 'ACC-LIST-001')
                    ->missing('balance')
                    ->etc()
                )
            );
    }

    /**
     * @return array{0: User, 1: Campaign}
     */
    private function superAdminWithCampaign(): array
    {
        $role = Role::query()->create([
            'name' => 'Super Administrator',
            'slug' => 'super-administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        $entity = Entity::query()->create([
            'entity_code' => 'TEST-ENT',
            'name' => 'Test Entity',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $campaign = Campaign::query()->create([
            'entity_id' => $entity->id,
            'campaign_code' => 'TEST-CAMP',
            'name' => 'Test Campaign',
            'status' => CampaignStatus::Active,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$user, $campaign];
    }
}
