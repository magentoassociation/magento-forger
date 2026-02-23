<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class Company extends Model
{
    /**
     * The attributes that aren't mass assignable.
     * The status attribute should only be set by admins through the Filament panel.
     *
     * @var list<string>
     */
    protected $guarded = ['status'];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Set the country_code attribute and automatically populate the country field with the full name.
     */
    public function setCountryCodeAttribute(?string $value): void
    {
        $this->attributes['country_code'] = $value;

        if ($value) {
            $country = collect(countries())->firstWhere('iso_3166_1_alpha3', $value);
            $this->attributes['country'] = $country ? $country['name'] : null;
        } else {
            $this->attributes['country'] = null;
        }
    }

    /**
     * Get the country_code attribute. Provides backward compatibility for existing records.
     */
    public function getCountryCodeAttribute(?string $value): ?string
    {
        // If country_code is already set, use it
        if ($value) {
            return $value;
        }

        // Backward compatibility: try to derive from country field
        if (isset($this->attributes['country'])) {
            $country = collect(countries())->first(fn($c) =>
                $c['iso_3166_1_alpha3'] === $this->attributes['country']
                || $c['name'] === $this->attributes['country']
            );

            return $country ? $country['iso_3166_1_alpha3'] : null;
        }

        return null;
    }

    public function affiliations(): HasMany
    {
        return $this->hasMany(CompanyAffiliation::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_owners')->withTimestamps();
    }
}
