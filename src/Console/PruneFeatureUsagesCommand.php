<?php

namespace MichaelLurquin\FeatureLimiter\Console;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use MichaelLurquin\FeatureLimiter\Models\FeatureUsage;

class PruneFeatureUsagesCommand extends Command
{
    protected $signature = 'feature-limiter:prune-usages
        {--days= : Remove usage rows older than X days}
        {--months= : Remove usage rows older than X months}
        {--years= : Remove usage rows older than X years}
        {--dry-run : Do not delete, only show how many rows would be deleted}
        {--prune-zero : Also delete rows where used = 0}';

    protected $description = 'Prune old feature usage rows according to retention rules';

    public function handle(): int
    {
        $enabled = (bool) config('feature-limiter.usage_retention.enabled', false);

        // si rien n’est passé en option et que retention disabled => ne fait rien
        if ( !$enabled && !$this->option('days') && !$this->option('months') && !$this->option('years') )
        {
            $this->info('Usage retention is disabled and no override was provided. Nothing to do.');
            return self::SUCCESS;
        }

        $cutoff = $this->resolveCutoffDate();
        if ( !$cutoff )
        {
            $this->error('No retention period provided (days/months/years).');
            return self::FAILURE;
        }

        $query = FeatureUsage::query()->whereDate('period_end', '<', $cutoff->toDateString());

        $pruneZero = (bool)($this->option('prune-zero') ?? config('feature-limiter.usage_retention.prune_zero_usage', false));
        if ( $pruneZero )
        {
            $query->where('used', '=', 0);
        }

        $count = (clone $query)->count();

        if ( $this->option('dry-run') )
        {
            $this->info("Dry run: {$count} row(s) would be deleted (period_end < {$cutoff->toDateString()}).");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} row(s) (period_end < {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }

    private function resolveCutoffDate(): ?Carbon
    {
        $days = $this->option('days') ?? config('feature-limiter.usage_retention.keep.days');
        $months = $this->option('months') ?? config('feature-limiter.usage_retention.keep.months');
        $years = $this->option('years') ?? config('feature-limiter.usage_retention.keep.years');

        $now = now();

        $candidates = [];

        if ( is_numeric($days) )
        {
            $candidates[] = $now->copy()->subDays((int) $days);
        }

        if ( is_numeric($months) )
        {
            $candidates[] = $now->copy()->subMonths((int) $months);
        }

        if ( is_numeric($years) )
        {
            $candidates[] = $now->copy()->subYears((int) $years);
        }

        if ( empty($candidates) ) return null;

        // Plus restrictive = la date la plus récente (on garde moins longtemps)
        usort($candidates, fn($a, $b) => $b->timestamp <=> $a->timestamp);

        return $candidates[0];
    }
}
