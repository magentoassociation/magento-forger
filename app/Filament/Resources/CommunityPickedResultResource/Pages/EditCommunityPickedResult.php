<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Filament\Resources\CommunityPickedResultResource\Pages;

use App\Filament\Resources\CommunityPickedResultResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPickedResult extends EditRecord
{
    protected static string $resource = CommunityPickedResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
