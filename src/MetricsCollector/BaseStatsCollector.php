<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

use TutuRu\Metrics\MetricsCollector;
use TutuRu\Metrics\MetricsExporterInterface;

abstract class BaseStatsCollector extends MetricsCollector
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


    public function export(?MetricsExporterInterface $exporter)
    {
        if (!is_null($exporter)) {
            $exporter->saveCollector($this);
        }
    }
}
