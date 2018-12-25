<?php
declare(strict_types=1);

namespace TutuRu\Redis;

interface RedisPushListInterface
{
    public function push($message): void;

    public function isAvailable(): bool;
}
