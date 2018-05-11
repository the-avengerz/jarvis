<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Paypal;

use FastD\Http\Request;
use Avengers\Jarvis\Exception\GatewayException;
use Avengers\Jarvis\Exception\GatewayMethodNotSupportException;
use Avengers\Jarvis\Exception\PaypalGetTransactionFailedAfterDoCharge;
use Avengers\Jarvis\AbstractGateway;
use Avengers\Jarvis\Requests\Charge;
use Avengers\Jarvis\Requests\Close;
use Avengers\Jarvis\Requests\Query;
use Avengers\Jarvis\Requests\Refund;

class ExpressCheckout extends AbstractGateway
{
    const NVP_GATEWAY = 'https://api-3t.paypal.com/nvp';

    const WEB_GATEWAY = 'https://www.paypal.com/cgi-bin/webscr';

    /**
     * paypal 快速结账的支付流程同其他支付渠道有差异, 需要从 paypal 下单获取 TOKEN 并引导用户跳转到 Paypal
     * 用户在 paypal 端授权后携带 TOKEN 跟 PAYERID 返回 return_url, 获取 TOKEN 跟 PAYERID 调取扣款.
     *
     * @param Charge $form
     *
     * @return array
     */
    public function charge(Charge $form)
    {
        if ($form->has('extras.TOKEN')) {
            return $this->doCharge($form);
        }

        return $this->setCharge($form);
    }

    /**
     * 退款.
     *
     * @param Refund $form
     *
     * @return array
     */
    public function refund(Refund $form)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * 关闭.
     *
     * @param Close $form
     *
     * @return array
     */
    public function close(Close $form)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * 查询.
     *
     * @param Query $form
     *
     * @return array
     */
    public function query(Query $form)
    {
        $result = $this->queryTransaction($form->get('order_id'), $form->get('extras'));

        if ('Completed' !== (isset($result['L_STATUS0']) ? $result['L_STATUS0'] : '')) {
            throw new GatewayException('transaction not found');
        }

        return [
            'order_id' => $form->get('order_id'),
            'status' => 'paid',
            'trade_sn' => $result['L_TRANSACTIONID0'],
            'buyer_identifiable_id' => $result['L_EMAIL0'],
            'buyer_name' => $result['L_EMAIL0'],
            'buyer_email' => $result['L_EMAIL0'],
            'amount' => $result['L_AMT0'],
            'tax' => abs(isset($result['L_FEEAMT0']) ? $result['L_FEEAMT0'] : 0),
            'raw' => $result,
        ];
    }

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
    public function chargeNotify(array $receives)
    {
        $transaction = $this->queryTransaction($receives['custom']);

        return [
            'order_id' => $receives['custom'],
            'status' => 'paid',
            'trade_sn' => $receives['txn_id'],
            'amount' => $receives['payment_gross'],
            'buyer_name' => $transaction['L_NAME0'],
            'buyer_email' => $transaction['L_EMAIL0'],
            'currency' => $receives['mc_currency'],
            'buyer_identifiable_id' => $transaction['L_EMAIL0'],
            'paid_at' => strtotime($receives['payment_date']),
            'tax' => $receives['payment_fee'],
            'raw' => [
                'notification' => $receives,
                'query' => $transaction,
            ],
        ];
    }

    /**
     * 退款通知, 并非所有支付渠道都支持
     *
     * @param $receives
     *
     * @return array
     */
    public function refundNotify(array $receives)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * 关闭通知, 并非所有支付渠道都支持
     *
     * @param $receives
     *
     * @return array
     */
    public function closeNotify(array $receives)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * 通知校验.
     *
     * @param $receives
     *
     * @return bool
     */
    public function verify($receives)
    {
        $response = (new Request('POST', self::WEB_GATEWAY))->send("cmd=_notify-validate&{$receives}");

        return 'VERIFIED' === (string) $response->getBody();
    }

    /**
     * 通知成功处理响应.
     *
     * @return string
     */
    public function success()
    {
        return 'SUCCESS.';
    }

    /**
     * 通知处理失败响应.
     *
     * @return string
     */
    public function fail()
    {
        return 'ERROR.';
    }

    /**
     * @param $receives
     *
     * @return array
     */
    public function convertNotificationToArray($receives)
    {
        parse_str($receives, $receives);

        return $receives;
    }

    /**
     * 此处本来可以通过 $_POST 获取消息, 但是验证时需要使用 raw 字符串进行验证, 因此直接获取 raw 字符串.
     *
     * @return string
     */
    public function receiveNotificationFromRequest()
    {
        return file_get_contents('php://input');
    }

    /**
     * @param Charge $form
     *
     * @return array
     */
    protected function setCharge(Charge $form)
    {
        $payload = $this->createPayload(
            array_merge(
                [
                    'METHOD' => 'SetExpressCheckout',
                    'AMT' => $form->get('amount'),
                    'CUSTOM' => $form->get('order_id'),
                    'CURRENCYCODE' => strtoupper($form->get('currency')),
                    'PAYMENTACTION' => 'Sale',
                    'CANCELURL' => $form->get('return_url'),
                    'RETURNURL' => $form->get('return_url'),
                    'INVNUM' => $form->get('order_id'),
                    'DESC' => $form->get('description'),
                    'BRANDNAME' => $this->config->get('brand_name'),
                    'NOSHIPPING' => 1,
                    'NOTIFYURL' => $this->config->get('notify_url'),
                    'PAYMENTREQUEST_0_NOTIFYURL' => $this->config->get('notify_url'),
                ],
                $form->get('extras')
            )
        );

        $result = $this->request(self::NVP_GATEWAY, $payload);

        return [
            'charge_url' => self::WEB_GATEWAY.'?'.http_build_query(
                [
                    'cmd' => '_express-checkout-mobile',
                    'useraction' => 'commit',
                    'token' => $result['TOKEN'],
                ]
            ),
        ];
    }

    /**
     * @param Charge $form
     *
     * @return array
     */
    protected function doCharge(Charge $form)
    {
        $payload = $this->createPayload(
            array_merge(
                [
                    'METHOD' => 'DoExpressCheckoutPayment',
                    'PAYMENTREQUEST_0_AMT' => $form->get('amount'),
                    // 'TOKEN' => $form->find('extras.token'),
                    // 'PAYERID' => $form->find('extras.payer_id'),
                ],
                $form->get('extras')
            )
        );

        /**
         * 请求扣款, 此时 paypal 并不会返回用户相关数据.
         */
        $result = $this->request(self::NVP_GATEWAY, $payload);

        /**
         * 查询交易, 获取用户相关数据.
         */
        $transaction = $this->queryTransaction($form->get('order_id'));

        if (!isset($transaction['L_EMAIL0'])) {
            throw new PaypalGetTransactionFailedAfterDoCharge("get transaction failed: {$form->get('order_id')}");
        }

        return [
            'charge_url' => '',
            'parameters' => [
                'order_id' => $form->get('order_id'),
                'status' => 'paid',
                'trade_sn' => $result['PAYMENTINFO_0_TRANSACTIONID'],
                'buyer_identifiable_id' => $transaction['L_EMAIL0'],
                'amount' => $result['PAYMENTINFO_0_AMT'],
                'tax' => $result['PAYMENTINFO_0_FEEAMT'],
                'currency' => $result['PAYMENTINFO_0_CURRENCYCODE'],
                'buyer_name' => $transaction['L_NAME0'],
                'buyer_email' => $transaction['L_EMAIL0'],
                'paid_at' => strtotime($result['TIMESTAMP']),
                'charge_raw' => $result,
                'query_raw' => $transaction,
            ],
        ];
    }

    /**
     * @param $orderId
     * @param array $extras
     *
     * @return array
     */
    protected function queryTransaction($orderId, array $extras = [])
    {
        $payload = $this->createPayload(
            array_merge(
                [
                    'METHOD' => 'TransactionSearch',
                    'STARTDATE' => date('Y-m-d\TH:i:s\Z', $this->config->get('started_at')),
                    'INVNUM' => $orderId,
                ],
                $extras
            )
        );

        $result = $this->request(self::NVP_GATEWAY, $payload);

        return $result;
    }

    /**
     * @param array $parameters
     *
     * @return array
     */
    protected function createPayload(array $parameters)
    {
        return array_merge(
            [
                'USER' => $this->config->get('user'),
                'PWD' => $this->config->get('password'),
                'SIGNATURE' => $this->config->get('signature'),
                'VERSION' => 88,
            ],
            $parameters
        );
    }

    /**
     * @param $url
     * @param $payload
     *
     * @return array
     */
    protected function request($url, $payload)
    {
        $request = (new Request('POST', $url));

        if ($this->config->get('by_proxy', false)) {
            $request->withOptions(
                [
                    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
                    CURLOPT_PROXY => $this->config->get('proxy'),
                ]
            );
        }

        $response = $request->send($payload);

        $result = [];

        parse_str((string) $response->getBody(), $result);

        if ('Success' !== (isset($result['ACK']) ? $result['ACK'] : '')) {
            throw new GatewayException('Paypal Gateway Error: '.$result['L_LONGMESSAGE0'], $result);
        }

        return $result;
    }
}
