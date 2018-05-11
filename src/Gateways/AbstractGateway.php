<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways;

use Avengers\Jarvis\Contracts\GatewayInterface;
use Avengers\Jarvis\Utils\Config;

abstract class AbstractGateway implements GatewayInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * AbstractGateway constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * 格式化消息.
     *
     * @param $receives
     *
     * @return array
     */
    abstract public function convertNotificationToArray($receives): array;
}
