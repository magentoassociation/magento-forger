<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string $github_id
 * @property string|null $github_username
 * @property bool $is_admin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @mixin Builder
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'github_id',
        'github_username',
    ];

    /**
     * The attributes that aren't mass assignable.
     * is_admin should only be set via the MakeUserAdmin command
     *
     * @var list<string>
     */
    protected $guarded = ['is_admin'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->getAttribute('is_admin');
    }

    /**
     * Whether this user may see the full maintainer board — every maintainer,
     * including idle ones with a zero score. The public sees only scoring
     * maintainers; admins, maintainers, and community council members see all.
     */
    public function canViewFullMaintainerBoard(): bool
    {
        return $this->is_admin || $this->hasActiveRole(['maintainer', 'community-council']);
    }

    /**
     * Whether this user may see Community-Picked PRs: admins and active community
     * council members. Council membership is read live, not from is_admin alone,
     * though admins keep access whatever their council status.
     */
    public function canViewCommunityPicked(): bool
    {
        return $this->is_admin || $this->hasActiveRole(['community-council']);
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasActiveRole(array $roles): bool
    {
        return $this->github_username !== null
            && RoleEligibility::query()
                ->whereIn('role', $roles)
                ->where('active', true)
                ->where('login', $this->github_username)
                ->exists();
    }
}
