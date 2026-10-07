<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function testAccountMenuExposesMenuRoles(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'github_username' => 'someviewer',
        ]));

        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('role="menu" aria-labelledby="acct-chip"', false)
            ->assertSee('class="dropdown-item acct-item" role="menuitem" tabindex="-1" href="'.route('leaderboard.detail', ['board' => 'contributor', 'login' => 'someviewer']).'">My contributions', false)
            ->assertSee('class="dropdown-item acct-item" role="menuitem" tabindex="-1" href="'.route('filament.admin.pages.dashboard').'">Admin', false)
            ->assertSee('role="menuitem" tabindex="-1">Logout', false);
    }

    public function testBrandLinksToNamedHomeRoute(): void
    {
        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertSee('class="navbar-brand site-brand" href="'.route('home').'"', false);
    }

    public function testGuestHasNoAccountMenu(): void
    {
        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertDontSee('role="menu"', false);
    }

    public function testScoringPageH1IsSentenceCase(): void
    {
        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertSee('<h1>How scoring works</h1>', false)
            ->assertSee('How scoring works</a>', false)
            ->assertDontSee('How does scoring work?');
    }

    public function testNavDropdownsCarryCaretGlyph(): void
    {
        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertSeeInOrder(['dropdown-toggle', 'Issues', '<span class="nav-caret" aria-hidden="true">▾</span>'], false)
            ->assertSeeInOrder(['dropdown-toggle', 'PRs', '<span class="nav-caret" aria-hidden="true">▾</span>'], false);
    }
}
