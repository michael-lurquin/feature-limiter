<?php

namespace MichaelLurquin\FeatureLimiter\Builders\Concerns;

use Illuminate\Database\Eloquent\Model;

trait UsesBuilderAttributes
{
    protected function fillAttributes(Model $model): Model
    {
        if ( !empty($this->attributes) )
        {
            $model->fill($this->attributes);
        }

        return $model;
    }
}
