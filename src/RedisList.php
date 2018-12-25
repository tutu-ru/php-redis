<?php
declare(strict_types=1);

namespace TutuRu\Redis;

class RedisList implements RedisPushListInterface
{
    /** @var Connection */
    private $connection;

    /** @var string */
    private $name;


    public function __construct(Connection $connection, string $name)
    {
        $this->connection = $connection;
        $this->name = $name;
    }


    public function getLength()
    {
        return $this->connection->llen($this->name);
    }


    public function push($value): void
    {
        $this->connection->lpush($this->name, [$value]);
    }


    public function pop()
    {
        return $this->connection->rpop($this->name);
    }


    public function del()
    {
        $this->connection->del([$this->name]);
    }


    public function isAvailable(): bool
    {
        return $this->connection->isAvailable();
    }
}
