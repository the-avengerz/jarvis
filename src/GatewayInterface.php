<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis;


use Avengers\Jarvis\Requests\Charge;
use Avengers\Jarvis\Requests\Refund;
use Avengers\Jarvis\Requests\Close;
use Avengers\Jarvis\Requests\Query;

/**
 * Interface GatewayInterface
 * @package Avengers\Jarvis
 */
interface GatewayInterface
{
    const PRODUCT_GATEWAY = '';
    const SANDBOX_GATEWAY = '';

    /**
     * 支付.
     *
     * @param Charge $form
     *
     * @return array
     */
    public function charge(Charge $form);

    /**
     * 退款.
     *
     * @param Refund $form
     *
     * @return array
     */
    public function refund(Refund $form);

    /**
     * 关闭.
     *
     * @param Close $form
     *
     * @return array
     */
    public function close(Close $form);

    /**
     * 查询.
     *
     * @param Query $form
     *
     * @return array
     */
    public function query(Query $form);

    /**
     * 支付通知, 触发通知根据不同支付渠道, 可能包含:
     * 1. 交易创建通知
     * 2. 交易关闭通知
     * 3. 交易支付通知.
     *
     * @param $receives
     *
     * @return array
     */
    public function chargeNotify(array $receives);

    /**
     * 退款通知, 并非所有支付渠道都支持
     *
     * @param $receives
     *
     * @return array
     */
    public function refundNotify(array $receives);

    /**
     * 关闭通知, 并非所有支付渠道都支持
     *
     * @param $receives
     *
     * @return array
     */
    public function closeNotify(array $receives);

    /**
     * 通知校验.
     *
     * @param $receives
     *
     * @return bool
     */
    public function verify($receives);

    /**
     * 通知成功处理响应.
     *
     * @return string
     */
    public function success();

    /**
     * 通知处理失败响应.
     *
     * @return string
     */
    public function fail();

    /**
     * 从请求中获取通知消息.
     *
     * @return array|string
     */
    public function receiveNotificationFromRequest();
}
