<?php
declare(strict_types=1);

namespace TutuRu\Redis\MetricsCollector;

class PushMetricCollector extends BaseMetricCollector
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


    public function failWith(\Throwable $exception)
    {
        $this->result = self::RESULT_FAIL;
        $this->failReason = $this->getFailReasonByException($exception);
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
                'result' => $this->result . ($this->failReason ? "_{$this->failReason}" : '')
            ]
        );
    }


    private function getFailReasonByException(\Throwable $exception): string
    {
        $shortClassName = substr(strrchr(get_class($exception), "\\"), 1);
        $shortClassName = str_replace('Exception', '', $shortClassName);
        return strtolower(preg_replace('#([a-z])([A-Z])#', "$1_$2", $shortClassName));
    }
}
