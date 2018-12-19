<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

class PushMetricsCollector extends BaseMetricsCollector
{
    private const RESULT_SUCCESS = 'success';
    private const RESULT_FAIL = 'fail';

    /** @var string */
    private $result;

    /** @var string */
    private $failReason;


    public function success()
    {
        $this->result = self::RESULT_SUCCESS;
    }


    public function failWith(string $failReason = null)
    {
        $this->result = self::RESULT_FAIL;
        $this->failReason = $failReason;
    }


    protected function getTimersMetricName(): string
    {
        return 'redis_write_duration';
    }


    protected function getTimersMetricTags(): array
    {
        return array_merge(
            parent::getTimersMetricTags(),
            [
                'result'      => $this->result,
                'fail_reason' => $this->failReason ?? 'no'
            ]
        );
    }
}
