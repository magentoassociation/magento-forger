<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable site setting, stored as a key/value pair.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    /** Y-m-d date the current Community-Picked voting period ends. */
    public const COMMUNITY_PICKED_VOTING_ENDS_ON = 'community_picked.voting_ends_on';

    protected $guarded = [];

    public static function valueOf(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
