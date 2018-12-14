<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use PHPUnit\Framework\TestCase;
use TutuRu\Config\ConfigContainer;
use TutuRu\Redis\ConnectionManager;
use TutuRu\Tests\Config\JsonConfig\JsonConfigFactory;
use TutuRu\Tests\Metrics\MemoryMetricsExporter\MemoryMetricsExporter;
use TutuRu\Tests\Metrics\MemoryMetricsExporter\MemoryMetricsExporterFactory;

abstract class BaseTest extends TestCase
{
    /** @var ConfigContainer */
    protected $config;

    /** @var MemoryMetricsExporter */
    protected $metricsExporter;

    public static function setUpBeforeClass()
    {
        RedisServer::getInstance('test-1')->run();
        RedisServer::getInstance('test-2')->run();
    }


    public function setUp()
    {
        parent::setUp();
        $this->config = JsonConfigFactory::createConfig(__DIR__ . "/config/application.json");
        $this->metricsExporter = MemoryMetricsExporterFactory::create($this->config);
    }


    protected function getConnectionManager(): ConnectionManager
    {
        return new ConnectionManager($this->config);
    }
}
