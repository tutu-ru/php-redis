<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

class RedisServer
{
    /** @var RedisServer */
    private static $instances = [];

    private $testPath;
    private $configName;
    private $isRun = false;

    private $tmpPath;
    private $redisPath;
    private $pidFile;
    private $configFile;


    public static function getInstance($configName): RedisServer
    {
        if (empty(self::$instances[$configName])) {
            self::$instances[$configName] = new self($configName);
        }
        return self::$instances[$configName];
    }

    public function __construct(string $configName)
    {
        $this->configName = $configName;
        $this->testPath = dirname(__FILE__);
        $this->tmpPath = $this->testPath . '/server/tmp/redis';
        $this->redisPath = $this->tmpPath . '/' . $this->configName;
        $this->pidFile = $this->redisPath . '/redis.pid';
        $this->configFile = $this->redisPath . '/config.conf';
    }


    public function run()
    {
        if ($this->isRun) {
            return;
        }
        $this->createEnvironment();
        $this->runRedisServer();
        if ($this->isRun) {
            register_shutdown_function([$this, 'shutdown']);
        }
    }


    public function shutdown()
    {
        $this->kill();
        exec("rm -rf {$this->redisPath}");
    }


    private function kill()
    {
        $pid = file_get_contents($this->pidFile);
        shell_exec('kill ' . $pid);
        $this->isRun = false;
    }


    private function runRedisServer()
    {
        $returnCode = 0;
        $result = [];
        exec('env redis-server ' . $this->configFile, $result, $returnCode);
        if ($returnCode != 0) {
            throw new \Exception(implode("\n", $result), $returnCode);
        }

        for ($i = 0; $i < 1000; $i++) {
            if (file_exists($this->pidFile)) {
                $this->isRun = true;
                break;
            }
            usleep(100);
        }

        if (!$this->isRun) {
            throw new \Exception('Fail run redis-server "' . $this->configName . '"', $returnCode);
        }
    }

    private function createEnvironment()
    {
        $returnCode = 0;
        $result = [];
        exec('which redis-server', $result, $returnCode);
        if ($returnCode != 0 || count($result) == 0) {
            throw new \Exception("Not found redis-server\n" . implode("\n", $result), $returnCode);
        }
        exec("mkdir -p {$this->redisPath}");
        $this->saveConfig();
    }

    private function saveConfig()
    {
        $content = file_get_contents($this->testPath . '/server/etc/redis/' . $this->configName . '.conf');
        $content = str_replace('{{DIR}}', $this->redisPath, $content);
        $content = str_replace('{{FILE_PID}}', $this->pidFile, $content);
        file_put_contents($this->configFile, $content);
    }
}
