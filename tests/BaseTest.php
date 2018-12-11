<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use PHPUnit\Framework\TestCase;
use TutuRu\Config\ConfigContainer;
use TutuRu\Redis\ConnectionManager;

abstract class BaseTest extends TestCase
{
    /** @var ConfigContainer */
    protected $config;

    public static function setUpBeforeClass()
    {
        RedisServer::getInstance('test-1')->run();
        RedisServer::getInstance('test-2')->run();
    }


    public function setUp()
    {
        parent::setUp();
        $this->config = new ConfigContainer();
        $this->config->setApplicationConfig(new TestConfig(__DIR__ . "/config/application.json"));
    }


    protected function getConnectionManager(): ConnectionManager
    {
        return new ConnectionManager($this->config);
    }
}
