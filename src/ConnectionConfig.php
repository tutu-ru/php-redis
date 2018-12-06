<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Redis\Exceptions\ConnectionConfigException;

class ConnectionConfig
{
    private $host;
    private $port;
    private $connectionTimeout;
    private $readWriteTimeout;
    private $persistent = false;


    public function __construct(string $host, int $port)
    {
        $this->setHost($host)->setPort($port);
    }


    public function setHost(string $host): ConnectionConfig
    {
        if (empty($host)) {
            throw new ConnectionConfigException("No host parameter in connection");
        }
        $this->host = $host;
        return $this;
    }


    public function setPort(int $port): ConnectionConfig
    {
        if ($port <= 0) {
            throw new ConnectionConfigException("Invalid port parameter in connection");
        }
        $this->port = $port;
        return $this;
    }


    public function setConnectionTimeout(float $timeout): ConnectionConfig
    {
        $this->connectionTimeout = $timeout;
        return $this;
    }


    public function setReadWriteTimeout(float $timeout): ConnectionConfig
    {
        $this->readWriteTimeout = $timeout;
        return $this;
    }


    public function setPersistent(bool $value): ConnectionConfig
    {
        $this->persistent = $value;
        return $this;
    }


    public function getHost(): string
    {
        return $this->host;
    }


    public function getPort(): int
    {
        return $this->port;
    }


    public function getConnectionTimeout(): ?float
    {
        return $this->connectionTimeout;
    }


    public function getReadWriteTimeout(): ?float
    {
        return $this->readWriteTimeout;
    }


    public function getPersistent(): bool
    {
        return $this->persistent;
    }
}
