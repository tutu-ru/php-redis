<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Metrics\MetricsCollector;

class ConnectionStatsCollector extends MetricsCollector
{
    /** @var string */
    private $prefix;

    /** @var string */
    private $postfix;


    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
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


    private function finalize($postfix)
    {
        $this->postfix = $postfix;
        $this->endTiming();
    }


    protected function getTimersMetricName(): string
    {
        return $this->prefix;
    }


    protected function getTimersMetricTags(): array
    {
        return ['write' => $this->postfix];
    }
}
