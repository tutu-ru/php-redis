<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

class PushStatsCollector extends BaseStatsCollector
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAIL = 'fail';

    public const FAIL_REASON_NO_CONNECTIONS = 'no_available_connections';

    /** @var string */
    private $result;

    /** @var string */
    private $failReason;


    public function setResult(string $result)
    {
        $this->result = $result;
    }


    public function setFailReason(string $failReason)
    {
        $this->failReason = $failReason;
    }


    protected function getTimersMetricName(): string
    {
        return 'redis_write_duration';
    }


    protected function getTimersMetricTags(): array
    {
        return parent::getTimersMetricTags() + ['result' => $this->result, 'fail_reason' => $this->failReason ?? 'no'];
    }
}
