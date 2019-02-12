<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Redis\Exceptions\RedisException;
use TutuRu\Redis\Exceptions\DisconnectException;
use TutuRu\Redis\Exceptions\ConnectionTimeoutException;
use TutuRu\Redis\Exceptions\ReadTimeoutException;
use TutuRu\Redis\Exceptions\WriteTimeoutException;
use Predis\PredisException;
use Predis\Client;

class Connection
{
    /** @var ConnectionConfig */
    private $config;

    /** @var Client */
    private $predisClient;

    /** @var RedisList[] */
    private $lists = [];

    /**
     * Время в timestamp когда можно будет попытаться отправить запрос по этому соединению
     * @var int
     */
    private $availabilityTime;


    public function __construct(ConnectionConfig $config)
    {
        $this->config = $config;
    }


    private function getPredisClient(bool $autoConnect = true): Client
    {
        try {
            if (empty($this->predisClient)) {
                $this->predisClient = new Client($this->getPredisParams($this->config));
            }
            if ($autoConnect && !$this->predisClient->isConnected()) {
                $this->predisClient->connect();
            }
            return $this->predisClient;
        } catch (PredisException $e) {
            throw $this->createWrapperException($e);
        }
    }


    private function createWrapperException(PredisException $e): RedisException
    {
        $message = $e->getMessage();
        if (strpos($message, 'Error while reading line from the server') !== false) {
            return new ReadTimeoutException($e->getMessage(), 10, $e);
        }

        if (strpos($message, 'Error while writing bytes to the server') !== false) {
            return new WriteTimeoutException($e->getMessage(), 11, $e);
        }

        if (mb_strpos($message, 'Время ожидания соединения истекло') !== false
            || mb_strpos($message, 'Connection timed out') !== false) {
            return new ConnectionTimeoutException($e->getMessage(), 1, $e);
        }
        return new RedisException($e->getMessage(), 20, $e);
    }


    public function __call($name, $arguments)
    {
        try {
            return call_user_func_array([$this->getPredisClient(), $name], $arguments);
        } catch (PredisException $e) {
            throw $this->createWrapperException($e);
        }
    }


    private function getPredisParams(ConnectionConfig $config): array
    {
        $result = [
            'host'               => $config->getHost(),
            'port'               => $config->getPort(),
            'persistent'         => $config->getPersistent(),
            'timeout'            => $config->getConnectionTimeout(),
            'read_write_timeout' => $config->getReadWriteTimeout(),
        ];
        foreach ($result as $key => $value) {
            if (empty($value)) {
                unset($result[$key]);
            }
        }
        return $result;
    }


    public function getList(string $name): RedisList
    {
        if (!array_key_exists($name, $this->lists)) {
            $this->lists[$name] = new RedisList($this, $name);
        }
        return $this->lists[$name];
    }


    public function set($key, $value, int $ttl)
    {
        $this->getPredisClient()->set($key, $value, "EX", $ttl);
    }


    public function close()
    {
        try {
            $this->getPredisClient(false)->disconnect();
        } catch (\Throwable $e) {
            throw new DisconnectException($e->getMessage(), $e->getCode(), $e);
        }
    }


    public function setAvailabilityTimeout(int $timeoutInSeconds)
    {
        $this->availabilityTime = time() + $timeoutInSeconds;
    }


    public function resetAvailabilityTimeout(): void
    {
        $this->availabilityTime = 0;
    }


    public function isAvailable(): bool
    {
        return empty($this->availabilityTime) || time() > $this->availabilityTime;
    }
}
