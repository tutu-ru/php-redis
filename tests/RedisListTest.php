<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

class RedisListTest extends BaseTest
{
    public function setUp()
    {
        parent::setUp();
        $this->removeLists();
    }


    public function testList()
    {
        $connectionManager = $this->getConnectionManager();
        $list = $connectionManager->getConnection("test-1")->getList("testlist");

        $this->assertEquals(0, $list->getLength());

        $list->push(1);
        $list->push(2);
        $this->assertEquals(2, $list->getLength());

        $this->assertEquals(1, $list->pop());
        $this->assertEquals(2, $list->pop());
    }


    public function testPopForEmptyList()
    {
        $connectionManager = $this->getConnectionManager();
        $list = $connectionManager->getConnection("test-1")->getList("testlist2");
        $this->assertNull($list->pop(), 'pop should return null for empty list');

        $connectionManager->getConnection("test-1")->getList("testlist2")->del();
    }


    public function testSeveralObjectsWorkWithOneRedisList()
    {
        $connectionManager = $this->getConnectionManager();
        $list1 = $connectionManager->getConnection("test-1")->getList("twin");
        $list2 = $connectionManager->getConnection("test-1")->getList("twin");

        $list1->push(1);
        $list2->push(2);
        $this->assertEquals(2, $list1->getLength(), 'both objects map to the same redis list');
        $this->assertEquals(2, $list2->getLength(), 'both objects map to the same redis list');
        $this->assertEquals(1, $list2->pop(), 'should return the value, added by another object');

        $connectionManager->getConnection("test-1")->getList("twin")->del();
    }


    private function removeLists()
    {
        $connectionManager = $this->getConnectionManager();
        $connectionManager->getConnection("test-1")->getList("testlist")->del();
        $connectionManager->getConnection("test-1")->getList("testlist2")->del();
        $connectionManager->getConnection("test-1")->getList("twin")->del();
    }
}
