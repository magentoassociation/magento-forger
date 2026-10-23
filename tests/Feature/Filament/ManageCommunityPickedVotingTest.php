<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ManageCommunityPickedVoting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageCommunityPickedVotingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function testSavesTheVotingEndDate(): void
    {
        Livewire::test(ManageCommunityPickedVoting::class)
            ->fillForm(['voting_ends_on' => '2026-10-31'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('2026-10-31', Setting::valueOf(Setting::COMMUNITY_PICKED_VOTING_ENDS_ON));
    }

    public function testLoadsTheSavedDateAndCanClearIt(): void
    {
        Setting::put(Setting::COMMUNITY_PICKED_VOTING_ENDS_ON, '2026-10-31');

        Livewire::test(ManageCommunityPickedVoting::class)
            ->assertFormSet(['voting_ends_on' => '2026-10-31'])
            ->fillForm(['voting_ends_on' => null])
            ->call('save');

        $this->assertNull(Setting::valueOf(Setting::COMMUNITY_PICKED_VOTING_ENDS_ON));
    }
}
