<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CommunityPickedResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One month's Community-Picked results, posted as a screenshot of the top-voted PRs.
 *
 * @property CarbonImmutable|Carbon $month First day of the voting month.
 * @property CarbonImmutable|Carbon $posted_on
 * @property string $image_path Path on the public disk.
 * @property bool $enabled Disabled months are hidden from the page.
 */
class CommunityPickedResult extends Model
{
    /** @use HasFactory<CommunityPickedResultFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'posted_on' => 'date',
            'enabled' => 'boolean',
        ];
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
