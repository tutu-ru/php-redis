<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

class ReconnectMetricCollector extends BaseMetricCollector
{
    protected function getTimersMetricName(): string
    {
        return 'redis_failed_write_duration';
    }
}
