<?php
declare(strict_types=1);

namespace TutuRu\Tests\Redis;

use TutuRu\Config\MutableApplicationConfigInterface;

/**
 * @todo move to Config library
 */
class TestConfig implements MutableApplicationConfigInterface
{
    /** @var string */
    private $filename;

    /** @var array|mixed */
    private $data = [];

    private $patchData = [];

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function setValue(string $configId, $value)
    {
        $this->patchData[$configId] = $value;
    }

    public function load()
    {
        $this->data = json_decode(file_get_contents($this->filename), true);
    }

    public function getValue(string $configId)
    {
        return $this->patchData[$configId] ?? $this->getValueStep($this->data, explode('.', $configId));
    }


    private function getValueStep($graph, array $path)
    {
        // полный путь привел к значению
        if (empty($path)) {
            return $graph;
        }
        // шаг рекурсии
        $currentAddress = array_shift($path);
        if (!is_array($graph) || !array_key_exists($currentAddress, $graph)) {
            return null;
        } else {
            return $this->getValueStep($graph[$currentAddress], $path);
        }
    }
}
