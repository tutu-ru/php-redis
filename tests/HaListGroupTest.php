<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use Predis\Connection\ConnectionException;
use TutuRu\Redis\Exceptions\ConnectionTimeoutException;
use TutuRu\Redis\Exceptions\NoAvailableConnectionsException;
use TutuRu\Redis\Exceptions\ReadTimeoutException;
use TutuRu\Redis\Exceptions\RedisException;

class HaListGroupTest extends BaseTest
{
    const LIST_NAME = 'test_list';


    public function tearDown()
    {
        $this->removeLists();
        parent::tearDown();
    }


    private function removeLists()
    {
        $connectionManager = $this->getConnectionManager();
        foreach (['test-1', 'test-2'] as $connectionName) {
            $connectionManager->getConnection($connectionName)->getList(self::LIST_NAME)->del();
        }
    }


    public function testNotFoundAvailableConnections()
    {
        $connectionManager = $this->getConnectionManager();
        $list = $connectionManager->createHaListGroup(
            self::LIST_NAME,
            ['fail-1', 'fail-2', 'google'],
            $this->statsdExporterClient
        );
        $list->setRetryTimeout(10);
        $list->setGroupName('redis_tests');
        $this->assertTrue($list->isAvailable());

        try {
            $list->push('{"test"=>"text"}');
            self::fail(NoAvailableConnectionsException::class . " not thrown");
        } catch (NoAvailableConnectionsException $e) {
            $this->assertFalse($list->isAvailable());
        }

        $this->statsdExporterClient->save();
        $this->assertCount(4, $this->statsdExporterClient->getExportedMetrics());
        $tags = ['storage_type' => 'redis_tests', 'app' => 'unknown'];

        $reconnectMetrics = $this->statsdExporterClient->getExportedMetrics('redis_failed_write_duration');
        $this->assertCount(3, $reconnectMetrics);
        $this->assertEquals($tags, $reconnectMetrics[0]->getTags());
        $this->assertEquals($tags, $reconnectMetrics[1]->getTags());
        $this->assertEquals($tags, $reconnectMetrics[2]->getTags());

        $pushMetrics = $this->statsdExporterClient->getExportedMetrics('redis_write_duration');
        $this->assertCount(1, $pushMetrics);
        $this->assertEquals($tags + ['result' => 'fail_no_available_connections'], $pushMetrics[0]->getTags());
    }


    public function testPushToOnlyAvailableList()
    {
        $connectionManager = $this->getConnectionManager();
        $list = $connectionManager->createHaListGroup(
            self::LIST_NAME,
            ['fail-1', 'test-1', 'test-2'],
            $this->statsdExporterClient
        );
        $list->setRetryTimeout(10);
        $countPush = 50;
        for ($i = 0; $i < $countPush; $i++) {
            $list->push('msg');
        }

        $messagesInTest1 = $connectionManager->getConnection('test-1')->getList(self::LIST_NAME)->getLength();
        $messagesInTest2 = $connectionManager->getConnection('test-2')->getList(self::LIST_NAME)->getLength();
        $this->assertTrue($messagesInTest1 == $countPush || $messagesInTest2 == $countPush);

        $this->statsdExporterClient->save();

        // 50 success writes + 1 possible reconnect
        $this->assertGreaterThanOrEqual(50, count($this->statsdExporterClient->getExportedMetrics()));
    }


    public function testRandomSelectConnection()
    {
        $connectionManager = $this->getConnectionManager();
        $connectionNames = ['test-1', 'test-2'];
        $connectionNamesPushed = [];
        $countPush = 10;
        $expectedConnectionPushedCount = 2;
        // В цикле для того чтобы рандомайзер соединений выпал на разные листы
        for ($numTry = 0; $numTry < 20; $numTry++) {
            $list = $connectionManager->createHaListGroup(
                self::LIST_NAME,
                $connectionNames,
                $this->statsdExporterClient
            );
            $list->setRetryTimeout(10);
            for ($i = 0; $i < $countPush; $i++) {
                $list->push('msg');
            }

            foreach ($connectionNames as $name) {
                if ($connectionManager->getConnection($name)->getList(self::LIST_NAME)->getLength() == $countPush) {
                    $connectionNamesPushed[] = $name;
                    $connectionNamesPushed = array_unique($connectionNamesPushed);
                    $connectionManager->getConnection($name)->del(self::LIST_NAME);
                }
            }

            if (count($connectionNamesPushed) == $expectedConnectionPushedCount) {
                break;
            }
        }
        $this->assertCount($expectedConnectionPushedCount, $connectionNamesPushed);
    }


    /**
     * @dataProvider emptyListDataProvider
     */
    public function testEmptyList($listName, $connections)
    {
        $this->expectException(RedisException::class);
        $list = $this->getConnectionManager()->createHaListGroup($listName, $connections, $this->statsdExporterClient);
        $list->push('{"test"=>"text"}');
    }


    public function emptyListDataProvider()
    {
        return [
            [self::LIST_NAME, []],
            [self::LIST_NAME, ['tutu.ru']],
            ['', []],
        ];
    }


    /**
     * @dataProvider connectionTimeoutProvider
     */
    public function testConnectionTimeout($connectionName, $expectedTimeout, $expectedException)
    {
        $connectionManager = $this->getConnectionManager();
        $startTime = microtime(true);
        $list = $connectionManager->createHaListGroup('test', [$connectionName], $this->statsdExporterClient);

        /** @var array|RedisException[] $exceptions */
        $exceptions = [];
        $list->setExceptionHandler(
            function (\Exception $e) use (&$exceptions) {
                $exceptions[] = $e;
            }
        );
        try {
            $list->push('{"test"=>"text"}');
        } catch (NoAvailableConnectionsException $e) {
        }

        $this->assertCount(2, $exceptions);
        $this->assertInstanceOf($expectedException, $exceptions[0]);
        $this->assertInstanceOf(ConnectionException::class, $exceptions[0]->getPrevious());
        $this->assertEquals($expectedTimeout, microtime(true) - $startTime, 'timeout', 0.5);
    }


    public function connectionTimeoutProvider()
    {
        return [
            ['fail-2', 3, ConnectionTimeoutException::class],
            ['fail-3', 5, ReadTimeoutException::class],
        ];
    }
}
