<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Metrics\MetricsCollector;
use TutuRu\Metrics\MetricType;

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
        $this->save();
    }

    protected function saveCustomMetrics(): void
    {
    }


    protected function getTimingKey(): string
    {
        $keyParts = [MetricType::TYPE_LOW_LEVEL];
        foreach (explode('.', $this->prefix) as $part) {
            $keyParts[] = $part;
        }
        $keyParts[] = 'write';
        $keyParts[] = $this->postfix;

        return $this->glueNamespaces($keyParts);
    }
}
