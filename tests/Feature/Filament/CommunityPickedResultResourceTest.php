<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\CommunityPickedResultResource\Pages\CreateCommunityPickedResult;
use App\Filament\Resources\CommunityPickedResultResource\Pages\EditCommunityPickedResult;
use App\Models\CommunityPickedResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommunityPickedResultResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function testUploadsAMonthsScreenshot(): void
    {
        Livewire::test(CreateCommunityPickedResult::class)
            ->fillForm([
                'month' => '2026-09',
                'posted_on' => '2026-10-01',
                'image_path' => UploadedFile::fake()->image('results.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $result = CommunityPickedResult::query()->sole();
        $this->assertSame('2026-09-01', $result->month->toDateString());
        $this->assertSame('2026-10-01', $result->posted_on->toDateString());
        $this->assertTrue($result->enabled);
        $this->assertStringStartsWith('community-picked/', $result->image_path);
        Storage::disk('public')->assertExists($result->image_path);
    }

    public function testRejectsASecondUploadForTheSameMonth(): void
    {
        CommunityPickedResult::factory()->create(['month' => '2026-09-01']);

        Livewire::test(CreateCommunityPickedResult::class)
            ->fillForm([
                'month' => '2026-09',
                'posted_on' => '2026-10-02',
                'image_path' => UploadedFile::fake()->image('results.png'),
            ])
            ->call('create')
            ->assertHasFormErrors(['month']);
    }

    public function testEditingKeepsTheMonth(): void
    {
        $result = CommunityPickedResult::factory()->create(['month' => '2026-09-01', 'posted_on' => '2026-10-01']);
        UploadedFile::fake()->image('results.png')->storeAs('', $result->image_path, 'public');

        Livewire::test(EditCommunityPickedResult::class, ['record' => $result->getKey()])
            ->assertFormSet(['month' => '2026-09'])
            ->fillForm(['posted_on' => '2026-10-03', 'enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-09-01', $result->refresh()->month->toDateString());
        $this->assertSame('2026-10-03', $result->posted_on->toDateString());
        $this->assertFalse($result->enabled);
    }
}
