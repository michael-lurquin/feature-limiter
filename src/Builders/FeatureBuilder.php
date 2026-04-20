<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesBuilderAttributes;

class FeatureBuilder
{
    use UsesBuilderAttributes;

    /**
     * Create a new feature builder.
     *
     * @param string $key Unique feature identifier (e.g. 'sites', 'storage', 'custom_code')
     * @param array $attributes Optional initial attributes for the feature
     */
    public function __construct(protected string $key, protected array $attributes = []) {}

    /**
     * Set the display name for the feature.
     *
     * If not specified, defaults to ucfirst($key) on save.
     *
     * @param string $name Display name (e.g. 'Number of Sites', 'Storage Limit')
     * @return self
     */
    public function name(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Set the description for the feature.
     *
     * Used for documentation and UI display.
     *
     * @param string|null $description Feature description or null to remove
     * @return self
     */
    public function description(?string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * Set the group/category for organizing features in comparison tables.
     *
     * Features with the same group will be displayed together in catalog outputs.
     *
     * @param string|null $group Group name (e.g. 'core', 'storage', 'integrations') or null
     * @return self
     */
    public function group(?string $group): self
    {
        $this->attributes['group'] = $group;

        return $this;
    }

    /**
     * Set the type of feature (determines quota type and validation rules).
     *
     * Accepts FeatureType enum OR a string like 'integer', 'boolean', 'storage'.
     *
     * @param FeatureType|string $type Feature type: INTEGER (numeric limits), BOOLEAN (yes/no), STORAGE (1GB, 500MB)
     * @return self
     * @throws InvalidArgumentException If type is not a valid FeatureType
     *
     * @example
     * ->type(FeatureType::INTEGER)    // Numeric limit
     * ->type(FeatureType::BOOLEAN)    // Yes/no feature
     * ->type(FeatureType::STORAGE)    // Storage units
     * ->type('integer')               // String shorthand
     */
    public function type(FeatureType|string $type): self
    {
        $typeEnum = $type instanceof FeatureType ? $type : FeatureType::tryFrom($type);

        if ( !$typeEnum )
        {
            throw new InvalidArgumentException("Invalid feature type: {$type}");
        }

        $this->attributes['type'] = $typeEnum;

        return $this;
    }

    /**
     * Set the unit label for displaying the feature (e.g. 'sites', 'MB', 'GB').
     *
     * Used for human-readable display only.
     *
     * @param string|null $unit Unit label (e.g. 'sites', 'pages', 'GB') or null
     * @return self
     */
    public function unit(?string $unit): self
    {
        $this->attributes['unit'] = $unit;

        return $this;
    }

    /**
     * Set how often usage resets for this feature.
     *
     * Accepts ResetPeriod enum OR a string like 'none', 'daily', 'monthly', 'yearly'.
     *
     * @param ResetPeriod|string $period Reset frequency: NONE (lifetime), DAILY, WEEKLY, MONTHLY, YEARLY
     * @return self
     * @throws InvalidArgumentException If period is not a valid ResetPeriod
     *
     * @example
     * ->reset(ResetPeriod::MONTHLY)   // Usage resets each month
     * ->reset(ResetPeriod::NONE)      // Lifetime limit (never resets)
     * ->reset('daily')                // String shorthand
     */
    public function reset(ResetPeriod|string $period): self
    {
        $periodEnum = $period instanceof ResetPeriod ? $period : ResetPeriod::tryFrom($period);

        if ( !$periodEnum )
        {
            throw new InvalidArgumentException("Invalid reset period: {$period}");
        }

        $this->attributes['reset_period'] = $periodEnum->value;

        return $this;
    }

    /**
     * Set the sort order for the feature in comparison tables.
     *
     * Features are sorted by this field (ascending). Lower values appear first.
     *
     * @param int $sort Sort position (e.g. 0, 1, 2)
     * @return self
     */
    public function sort(int $sort): self
    {
        $this->attributes['sort'] = $sort;

        return $this;
    }

    /**
     * Set whether the feature is active/available.
     *
     * Inactive features won't appear in pricing catalogs by default.
     *
     * @param bool $active Default: true. Pass false to deactivate the feature
     * @return self
     */
    public function active(bool $active = true): self
    {
        $this->attributes['active'] = $active;

        return $this;
    }

    /**
     * Create or update the feature in the database.
     *
     * If a feature with this key already exists, it will be updated. Otherwise, it will be created.
     * Auto-generates label from key (ucfirst) if not explicitly set.
     *
     * @return Feature The created or updated Feature model instance
     *
     * @example
     * FeatureLimiter::feature('sites')
     *     ->type(FeatureType::INTEGER)
     *     ->group('core')
     *     ->save();
     */
    public function save(): Feature
    {
        $feature = Feature::query()->firstOrNew(['key' => $this->key]);

        if ( empty($this->attributes['label']) ) $this->attributes['label'] = ucfirst($this->key);

        $feature->fill($this->attributes);

        $feature->save();

        return $feature;
    }
}
