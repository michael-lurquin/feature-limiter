<?php

namespace MichaelLurquin\FeatureLimiter\Billing;

use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;

class BillingManager
{
    private array $providers = [];

    public function __construct(protected Container $app) {}

    public function driver(?string $name = null): BillingProvider
    {
        $name ??= config('feature-limiter.billing.default', 'cashier');

        if ( isset($this->providers[$name]) )
        {
            return $this->providers[$name];
        }

        $class = config("feature-limiter.billing.providers.{$name}");

        if ( !$class )
        {
            throw new InvalidArgumentException("Billing provider [{$name}] is not configured.");
        }

        $provider = $this->app->make($class);

        if ( !$provider instanceof BillingProvider )
        {
            throw new InvalidArgumentException("Billing provider [{$name}] must implement BillingProvider.");
        }

        $this->providers[$name] = $provider;

        return $provider;
    }

    public function default(): BillingProvider
    {
        return $this->driver();
    }

    public function provider(?string $name = null): BillingProvider
    {
        return $this->driver($name);
    }
}
