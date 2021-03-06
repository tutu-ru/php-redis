<?php
declare(strict_types=1);

namespace TutuRu\Redis;

use TutuRu\Config\ConfigInterface;
use TutuRu\Config\Exception\ConfigPathNotExistExceptionInterface;
use TutuRu\Metrics\StatsdExporterClientInterface;
use TutuRu\Redis\Exceptions\ConnectionConfigException;

class ConnectionManager
{
    /** @var ConnectionConfig[] */
    private $connectionsConfigs = [];

    /** @var Connection[] */
    private $connections = [];

    /** @var ConfigInterface */
    private $config;


    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->loadConnectionsConfig();
    }


    public function getConnection(string $name): Connection
    {
        if (!array_key_exists($name, $this->connections)) {
            $this->connections[$name] = $this->connect($name);
        }
        return $this->connections[$name];
    }


    public function closeConnection(string $name): void
    {
        if (array_key_exists($name, $this->connections)) {
            $slaughter = $this->connections[$name];
            unset($this->connections[$name]);
            $slaughter->close();
        }
    }


    public function createHaPushListGroup(
        string $listName,
        array $connectionNames,
        ?StatsdExporterClientInterface $statsdExporterClient = null
    ): HaPushListGroup {
        $haListGroup = new HaPushListGroup($this, $listName, $connectionNames);
        if (!is_null($statsdExporterClient)) {
            $haListGroup->setStatsdExporterClient($statsdExporterClient);
        }
        return $haListGroup;
    }


    private function connect(string $connectionName)
    {
        return new Connection($this->getConnectionConfig($connectionName));
    }


    private function loadConnectionsConfig()
    {
        foreach ($this->readConnectionsConfig() as $connectionName => $params) {
            try {
                $cfg = new ConnectionConfig((string)($params['host'] ?? ''), (int)($params['port'] ?? 0));
                if (!empty($params['timeout'])) {
                    $cfg->setConnectionTimeout((float)$params['timeout']);
                }
                if (!empty($params['read_write_timeout'])) {
                    $cfg->setReadWriteTimeout((float)$params['read_write_timeout']);
                }
                if (!empty($params['persistent'])) {
                    $cfg->setPersistent((bool)$params['persistent']);
                }
                $this->connectionsConfigs[$connectionName] = $cfg;
            } catch (ConnectionConfigException $e) {
                throw new ConnectionConfigException(
                    "Wrong connection params for connection [$connectionName]" . $e->getMessage()
                );
            }
        }
    }


    private function readConnectionsConfig(): array
    {
        $connections = null;
        try {
            $connections = $this->config->getValue('redis.connections', true);
        } catch (ConfigPathNotExistExceptionInterface $e) {
            throw new ConnectionConfigException("No connections config", $e->getCode(), $e);
        }
        if (!is_array($connections)) {
            throw new ConnectionConfigException("Invalid connections config: " . json_encode($connections));
        }
        return $connections;
    }


    private function getConnectionConfig(string $connectionName): ConnectionConfig
    {
        if (!array_key_exists($connectionName, $this->connectionsConfigs)) {
            throw new ConnectionConfigException("Undefined connection name: " . $connectionName);
        }
        return $this->connectionsConfigs[$connectionName];
    }
}
