<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

use TutuRu\Metrics\MetricCollector;

abstract class BaseMetricsCollector extends MetricCollector
{
    /** @var string */
    protected $storageType;

    public function __construct(?string $storageType = null)
    {
        $this->storageType = $storageType ?? 'unknown';
    }


    protected function getTimersMetricTags(): array
    {
        return ['storage_type' => $this->storageType];
    }
}
