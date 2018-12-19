<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Metrics\MetricsAwareInterface;
use TutuRu\Metrics\MetricsAwareTrait;
use TutuRu\Redis\Exceptions\DisconnectException;
use TutuRu\Redis\Exceptions\NoAvailableConnectionsException;
use TutuRu\Redis\Exceptions\RedisException;
use TutuRu\Redis\MetricsCollector\PushMetricsCollector;
use TutuRu\Redis\MetricsCollector\ReconnectMetricsCollector;

class HaSingleListPush implements MetricsAwareInterface
{
    use MetricsAwareTrait;

    private const FAIL_REASON_NO_CONNECTIONS = 'no_available_connections';

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
        $reconnectCollector = $this->initReconnectCollector();

        $lastException = null;
        $success = false;
        $failReason = null;
        $tryCount = count($this->connectionNames);
        for ($i = 0; $i <= $tryCount; $i++) {
            if ($i > 0) {
                $this->registerReconnect($reconnectCollector);
                $reconnectCollector = $this->initReconnectCollector();
            }

            try {
                $connection = $this->getConnection();
                $connection->getList($this->listName)->push($message);
                $success = true;
                break;
            } catch (NoAvailableConnectionsException $e) {
                $lastException = $e;
                $this->processException($e);
                $failReason = self::FAIL_REASON_NO_CONNECTIONS;
                break;
            } catch (\Exception $e) {
                // может быть как ошибка соединения, так и ошибка записи в редис
                $this->markCurrentConnectionUnavailable();
                $lastException = $e;
                $this->processException($e);
            }
        }

        if (!is_null($this->metricsExporter)) {
            $pushCollector->endTiming();
            if ($success) {
                $pushCollector->success();
            } else {
                $pushCollector->failWith($failReason);
            }
            $pushCollector->sendTo($this->metricsExporter);
        }

        if (!$success) {
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
        if ($this->metricsExporter) {
            $reconnectCollector->sendTo($this->metricsExporter);
        }
    }
}
