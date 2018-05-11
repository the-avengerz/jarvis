<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis;

use Avengers\Jarvis\Utils\Config;

/**
 * Class AbstractGateway
 * @package Avengers\Jarvis
 */
abstract class AbstractGateway implements GatewayInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var bool
     */
    protected $gateway_url;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * AbstractGateway constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->gateway_url = static::PRODUCT_GATEWAY;
    }

    /**
     * Enable payment debug mode.
     *
     * @return $this
     */
    public function enableDebug()
    {
        $this->gateway_url = static::SANDBOX_GATEWAY;

        $this->debug = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * 格式化消息.
     *
     * @param $receives
     *
     * @return array
     */
    abstract public function convertNotificationToArray($receives);
}
