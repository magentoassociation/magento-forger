<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Sets when the current Community-Picked voting period ends, shown under the page intro.
 *
 * @property Form $form
 */
class ManageCommunityPickedVoting extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Community-Picked voting';

    protected static ?string $title = 'Community-Picked voting';

    protected static string $view = 'filament.pages.manage-community-picked-voting';

    /** @var array{voting_ends_on?: string|null} */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(['voting_ends_on' => Setting::valueOf(Setting::COMMUNITY_PICKED_VOTING_ENDS_ON)]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('voting_ends_on')
                    ->label('Voting ends on')
                    ->native()
                    ->helperText('Shown as "Voting ends on <date>." under the intro. Leave empty to hide it.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Setting::put(Setting::COMMUNITY_PICKED_VOTING_ENDS_ON, $this->form->getState()['voting_ends_on'] ?: null);

        Notification::make()->success()->title('Saved')->send();
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [Action::make('save')->submit('save')];
    }
}
