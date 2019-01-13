<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use PHPUnit\Framework\TestCase;
use TutuRu\Config\JsonConfig\MutableJsonConfig;
use TutuRu\Redis\ConnectionManager;
use TutuRu\Tests\Metrics\MemoryMetricExporter\MemoryMetricExporter;
use TutuRu\Tests\Metrics\MemoryMetricExporter\MemoryMetricExporterFactory;

abstract class BaseTest extends TestCase
{
    /** @var MutableJsonConfig */
    protected $config;

    /** @var MemoryMetricExporter */
    protected $statsdExporterClient;

    public static function setUpBeforeClass()
    {
        RedisServer::getInstance('test-1')->run();
        RedisServer::getInstance('test-2')->run();
    }


    public function setUp()
    {
        parent::setUp();
        $this->config = new MutableJsonConfig(__DIR__ . "/config/application.json");
        $this->statsdExporterClient = MemoryMetricExporterFactory::create($this->config);
    }


    protected function getConnectionManager(): ConnectionManager
    {
        return new ConnectionManager($this->config);
    }
}
