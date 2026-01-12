<?php

namespace MichaelLurquin\FeatureLimiter\Builders\Concerns;

use MichaelLurquin\FeatureLimiter\Support\FeatureValueParser;

trait UsesFeatureValueParser
{
    private ?FeatureValueParser $featureValueParser = null;

    protected function featureValueParser(): FeatureValueParser
    {
        if ( $this->featureValueParser )
        {
            return $this->featureValueParser;
        }

        $this->featureValueParser = new FeatureValueParser();

        return $this->featureValueParser;
    }
}
