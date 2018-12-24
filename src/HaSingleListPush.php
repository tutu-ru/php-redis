<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Metrics\MetricAwareInterface;
use TutuRu\Metrics\MetricAwareTrait;
use TutuRu\Redis\Exceptions\DisconnectException;
use TutuRu\Redis\Exceptions\NoAvailableConnectionsException;
use TutuRu\Redis\Exceptions\RedisException;
use TutuRu\Redis\MetricsCollector\PushMetricsCollector;
use TutuRu\Redis\MetricsCollector\ReconnectMetricsCollector;

class HaSingleListPush implements MetricAwareInterface
{
    use MetricAwareTrait;

    /** @var ConnectionManager */
    private $connectionManager;

    /** @var string */
    private $listName;

    /** @var array */
    private $connectionNames = [];

    /** @var string */
    private $currentConnectionName;

    /** @var string */
    private $storageType;

    /** @var int */
    private $retryTimeoutInSeconds = 3;

    /** @var callable|null */
    private $exceptionHandler;


    public function __construct(ConnectionManager $connectionManager, string $listName, array $connectionNames)
    {
        if (empty($listName)) {
            throw new RedisException('Empty name list');
        }
        if (empty($connectionNames)) {
            throw new RedisException('Empty connections list');
        }
        $this->connectionManager = $connectionManager;
        $this->listName = $listName;
        shuffle($connectionNames);
        $this->connectionNames = $connectionNames;
    }


    public function setStorageType(string $storageType)
    {
        $this->storageType = $storageType;
    }


    public function setRetryTimeout(int $timeoutInSeconds)
    {
        $this->retryTimeoutInSeconds = $timeoutInSeconds;
    }


    public function setExceptionHandler(callable $handler)
    {
        $this->exceptionHandler = $handler;
    }


    public function push($message)
    {
        $pushCollector = new PushMetricsCollector($this->storageType);
        $pushCollector->startTiming();

        $lastException = null;
        $tryCount = count($this->connectionNames);
        for ($i = 0; $i <= $tryCount; $i++) {
            $reconnectCollector = $this->initReconnectCollector();
            try {
                $connection = $this->getConnection();
                $connection->getList($this->listName)->push($message);
                $lastException = null;
                break;
            } catch (NoAvailableConnectionsException $e) {
                $lastException = $e;
                $this->processException($e);
                break;
            } catch (\Throwable $e) {
                // может быть как ошибка соединения, так и ошибка записи в редис
                $this->markCurrentConnectionUnavailable();
                $lastException = $e;
                $this->processException($e);
            }
            $this->registerReconnect($reconnectCollector);
        }

        $this->registerPushResult($pushCollector, $lastException);
        if (!is_null($lastException)) {
            throw $lastException;
        }
    }


    public function isAvailable(): bool
    {
        try {
            if ($this->getConnection()) {
                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }


    private function markCurrentConnectionUnavailable()
    {
        if (is_null($this->currentConnectionName)) {
            return;
        }

        $name = $this->currentConnectionName;
        $this->currentConnectionName = null;

        try {
            $connection = $this->connectionManager->getConnection($name);
            $connection->setAvailabilityTimeout($this->retryTimeoutInSeconds);
            $connection->close();
        } catch (DisconnectException $e) {
            // Тут ничего не делаем, надеемся что DisconnectException сам о себе сообщит
        }
    }

    private function getConnection(): Connection
    {
        if ($this->currentConnectionName) {
            $connection = $this->connectionManager->getConnection($this->currentConnectionName);
            if ($connection->isAvailable()) {
                return $connection;
            }
        }

        foreach ($this->connectionNames as $name) {
            $connection = $this->connectionManager->getConnection($name);
            if ($connection->isAvailable()) {
                $this->currentConnectionName = $name;
                return $connection;
            }
        }

        throw new NoAvailableConnectionsException("No available connections for Redis");
    }


    private function processException(\Exception $exception)
    {
        if (!is_null($this->exceptionHandler)) {
            call_user_func($this->exceptionHandler, $exception);
        }
    }


    private function initReconnectCollector(): ReconnectMetricsCollector
    {
        $reconnectCollector = new ReconnectMetricsCollector($this->storageType);
        $reconnectCollector->startTiming();
        return $reconnectCollector;
    }


    private function registerReconnect(ReconnectMetricsCollector $reconnectCollector)
    {
        $reconnectCollector->endTiming();
        if ($this->statsdExporterClient) {
            $reconnectCollector->sendToStatsdExporter($this->statsdExporterClient);
        }
    }


    private function registerPushResult(PushMetricsCollector $pushStats, ?\Throwable $lastException): void
    {
        if (is_null($this->statsdExporterClient)) {
            return;
        }

        $pushStats->endTiming();
        if (is_null($lastException)) {
            $pushStats->success();
        } else {
            $pushStats->failWith($lastException);
        }
        $pushStats->sendToStatsdExporter($this->statsdExporterClient);
    }
}
