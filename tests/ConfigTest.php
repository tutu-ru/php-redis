<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use TutuRu\Config\JsonConfig\MutableJsonConfig;
use TutuRu\Redis\ConnectionManager;
use TutuRu\Redis\Exceptions\ConnectionConfigException;

class ConfigTest extends BaseTest
{
    public function configsDataProvider()
    {
        return [
            [__DIR__ . "/config/no-connections.json"],
            [__DIR__ . "/config/invalid-connections.json"],
            [__DIR__ . "/config/invalid-connection.json"],
        ];
    }


    /**
     * @dataProvider configsDataProvider
     */
    public function testInvalidConfig($configFile)
    {
        $this->expectException(ConnectionConfigException::class);

        $config = new MutableJsonConfig($configFile);
        new ConnectionManager($config);
    }
}
