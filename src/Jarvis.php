<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis;

use Avengers\Jarvis\Exception\InvalidNotificationException;
use Avengers\Jarvis\Utils\AbstractOption;
use Avengers\Jarvis\Utils\Collection;
use InvalidArgumentException;
use Avengers\Jarvis\Utils\Config;
use Avengers\Jarvis\Utils\Str;

class Jarvis implements AvengerJarvisInterface
{
    /**
     * @var Collection
     */
    protected $config;

    /**
     * @var GatewayInterface
     */
    protected $gateway;

    /**
     * @var array
     */
    protected static $extendGateways = [];

    /**
     * Cashier constructor.
     *
     * @param string $gateway
     * @param array  $config
     */
    public function __construct($gateway, array $config)
    {
        $this->config = new Config($config);
        $this->gateway = $this->makeGateway($gateway);
    }

    /**
     * @return $this
     */
    public function enableDebug()
    {
        $this->gateway->enableDebug();

        return $this;
    }

    /**
     * @param $method
     * @param $receives
     *
     * @return AbstractOption
     */
    public function notify($method, $receives = null)
    {
        is_null($receives) && $receives = $this->gateway->receiveNotificationFromRequest();

        if (empty($receives)) {
            throw new InvalidNotificationException('empty notification');
        }

        if (!$this->gateway->verify($receives)) {
            throw new InvalidNotificationException();
        }

        $receives = $this->gateway->convertNotificationToArray($receives);

        $gatewayMethod = "{$method}Notify";

        return $this->makeOption(
            'notification',
            $method,
            $this->gateway->$gatewayMethod($receives)
        );
    }

    /**
     * @param string $type
     * @param string $method
     * @param array  $data
     *
     * @return AbstractOption
     */
    protected function makeOption($type, $method, array $data)
    {
        $class = __NAMESPACE__.'\\'.ucfirst($type).'s\\'.ucfirst($method);

        if (!class_exists($class)) {
            throw new InvalidArgumentException("class {$class} not exists");
        }

        return new $class($data);
    }

    /**
     * @param string $channel
     *
     * @return AbstractGateway
     */
    protected function makeGateway($channel)
    {
        list($platform, $gateway) = explode('_', $channel, 2);

        $gateway = Str::studly($gateway);

        $class = __NAMESPACE__.'\\Gateways\\'.ucfirst($platform).'\\'.$gateway;

        if (!class_exists($class)) {
            if (!array_key_exists($channel, self::$extendGateways)) {
                throw new InvalidArgumentException("gateway {$channel} is not supported");
            }
            $class = self::$extendGateways[$class];
        }

        return new $class($this->config);
    }

    /**
     * @return string
     */
    public function success()
    {
        return $this->gateway->success();
    }

    /**
     * @return string
     */
    public function fail()
    {
        return $this->gateway->fail();
    }

    /**
     * @param $order_id
     * @return AbstractOption
     */
    public function query($order_id)
    {
        return $this->makeResponse('query', $order);
    }

    /**
     * @param $order_id
     * @return AbstractOption
     */
    public function refund($order_id)
    {
        return $this->makeResponse('refund', $order);
    }

    /**
     * @param array $order
     * @return AbstractOption
     */
    public function charge(array $order)
    {
        return $this->makeResponse('charge', $order);
    }

    /**
     * @param $method
     * @param $order
     * @return AbstractOption
     */
    protected function makeResponse($method, $order)
    {
        $request = $this->makeOption(
            'request',
            $method,
            $order
        );

        $response = $this->gateway->charge($request);

        return $this->makeOption(
            'response',
            $method,
            $response
        );
    }
}
