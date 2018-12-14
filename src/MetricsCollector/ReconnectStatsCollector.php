<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

class ReconnectStatsCollector extends BaseStatsCollector
{
    public function registerReconnect()
    {
        $this->endTiming();
        $this->save();
    }


    protected function getTimersMetricName(): string
    {
        return 'redis_failed_write_duration';
    }
}
