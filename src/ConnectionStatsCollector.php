<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Metrics\MetricsCollector;

class ConnectionStatsCollector extends MetricsCollector
{
    /** @var string */
    private $storageType;

    /** @var string */
    private $result;


    public function __construct(?string $storageType = null)
    {
        $this->storageType = $storageType ?? 'unknown';
        $this->startTiming();
    }


    public function registerReconnect()
    {
        $this->finalize('reconnect');
    }


    public function registerNoAvailableConnections()
    {
        $this->finalize('no_available_connections');
    }


    public function registerSuccess()
    {
        $this->finalize('success');
    }


    public function registerFail()
    {
        $this->finalize('fail');
    }


    private function finalize($result)
    {
        $this->result = $result;
        $this->endTiming();
    }


    protected function getTimersMetricName(): string
    {
        return 'redis_write_duration';
    }


    protected function getTimersMetricTags(): array
    {
        return ['storage_type' => $this->storageType, 'result' => $this->result];
    }
}
