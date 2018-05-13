<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Wechat;

use FastD\Http\Request;
use Avengers\Jarvis\Exception\GatewayException;
use Avengers\Jarvis\AbstractGateway;
use Avengers\Jarvis\Requests\Charge;
use Avengers\Jarvis\Requests\Close;
use Avengers\Jarvis\Requests\Query;
use Avengers\Jarvis\Requests\Refund;

abstract class AbstractWechatGateway extends AbstractGateway
{
    const MCH_APPLY_ORDER = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    const MCH_QUERY_ORDER = 'https://api.mch.weixin.qq.com/pay/orderquery';

    const MCH_CLOSE_ORDER = 'https://api.mch.weixin.qq.com/pay/closeorder';

    const MCH_APPLY_REFUND = 'https://api.mch.weixin.qq.com/secapi/pay/refund';

    const MCH_QUERY_REFUND = 'https://api.mch.weixin.qq.com/pay/refundquery';

    const MP_JSAPI_AUTH_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * 支付.
     *
     * @param Charge $form
     *
     * @return array
     */
    public function charge(Charge $form)
    {
        $payload = $this->createPayload(
            array_merge(
                [
                    'body' => $form->get('subject'),
                    'out_trade_no' => $form->get('order_id'),
                    'fee_type' => $form->get('currency'),
                    'total_fee' => $this->formatAmount($form->get('amount')),
                    'spbill_create_ip' => $form->get('user_ip'),
                    'trade_type' => $this->getTradeType(),
                    'notify_url' => $this->config->get('notify_url'),
                    'detail' => $form->get('description'),
                ],
                $this->prepareCharge($form)
            )
        );

        $response = $this->request(self::MCH_APPLY_ORDER, $payload);

        return $this->doCharge($response, $form);
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
        $payload = $this->createPayload(
            array_merge(
                [
                    'out_trade_no' => $form->get('order_id'),
                    'out_refund_no' => $form->get('refund_id'),
                    'total_fee' => $this->formatAmount($form->get('total_amount')),
                    'refund_fee' => $this->formatAmount($form->get('refund_amount')),
                    'refund_desc' => $form->get('reason'),
                ],
                $form->get('extras')
            )
        );

        $response = $this->request(
            self::MCH_APPLY_REFUND,
            $payload,
            $this->config->get('cert'),
            $this->config->get('ssl_key')
        );

        return [
            'refund_sn' => $response['refund_id'],
            'refund_amount' => ($response['coupon_refund_fee'] + $response['cash_refund_fee']) / 100,
            'raw' => $response,
        ];
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
        $payload = $this->createPayload(
            [
                'out_trade_no' => $form->get('order_id'),
            ]
        );

        $this->request(self::MCH_CLOSE_ORDER, $payload);

        return [];
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
        $parameters = [
            'appid' => $this->config->get('app_id'),
            'mch_id' => $this->config->get('mch_id'),
            'out_trade_no' => $form->get('order_id'),
            'nonce_str' => uniqid(),
            'sign_type' => 'MD5',
        ];
        $parameters['sign'] = $this->sign($parameters, $this->config->get('mch_secret'));

        $result = $this->request(self::MCH_QUERY_ORDER, $parameters);

        $amount = 0;
        $status = $this->formatTradeStatus($result['trade_state']);

        if ('paid' === $status) {
            $amount = ($result['cash_fee'] + ($result['coupon_fee'] ?? 0)) / 100;
        }

        return [
            'order_id' => $result['out_trade_no'],
            'status' => $status,
            'trade_sn' => $result['transaction_id'] ?? '',
            'buyer_identifiable_id' => $result['openid'] ?? '',
            'buyer_is_subscribed' => (isset($result['is_subscribe']) ? ('Y' === $result ? 'yes' : 'no') : 'no'),
            'amount' => $amount,
            'buyer_name' => '',
            'paid_at' => (isset($result['time_end']) ? strtotime($result['time_end']) : 0),
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
        $amount = ($receives['cash_fee'] + ($receives['coupon_fee'] ?? 0)) / 100;

        return [
            'order_id' => $receives['out_trade_no'],
            'status' => 'paid', // 微信只推送支付完成
            'trade_sn' => $receives['transaction_id'],
            'buyer_identifiable_id' => $receives['openid'],
            'buyer_is_subscribed' => 'N' === $receives['is_subscribe'] ? 'no' : 'yes',
            'amount' => $amount,
            'buyer_name' => '',
            'paid_at' => (isset($receives['time_end']) ? strtotime($receives['time_end']) : 0),
            'raw' => $receives,
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
        // TODO
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
        throw new GatewayException('Wechat channels are not supported to send close notify');
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
        $receives = $this->parseXml($receives);

        return $receives['sign'] === $this->sign($receives);
    }

    /**
     * 通知成功处理响应.
     *
     * @return string
     */
    public function success()
    {
        return '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }

    /**
     * 通知处理失败响应.
     *
     * @return string
     */
    public function fail()
    {
        return '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[wrong]]></return_msg></xml>';
    }

    /**
     * @param $receives
     *
     * @return array
     */
    public function convertNotificationToArray($receives)
    {
        return $this->parseXml($receives);
    }

    /**
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
    abstract protected function prepareCharge(Charge $form);

    /**
     * @param array  $response
     * @param Charge $form
     *
     * @return array
     */
    abstract protected function doCharge(array $response, Charge $form);

    /**
     * @return string
     */
    abstract protected function getTradeType();

    /**
     * @param array $payload
     *
     * @return array
     */
    protected function createPayload(array $payload)
    {
        $payload = array_merge(
            [
                'appid' => $this->config->get('app_id'),
                'mch_id' => $this->config->get('mch_id'),
                'nonce_str' => uniqid(),
                'sign_type' => 'MD5',
            ],
            $payload
        );
        $payload['sign'] = $this->sign($payload);

        return $payload;
    }

    /**
     * @param array $parameters
     *
     * @return string
     */
    protected function sign(array $parameters)
    {
        unset($parameters['sign']);
        ksort($parameters);
        $parameters['key'] = $this->config->get('mch_secret');

        return strtoupper(
            md5(
                urldecode(
                    http_build_query(
                        array_filter(
                            $parameters,
                            function ($value) {
                                return '' !== $value;
                            }
                        )
                    )
                )
            )
        );
    }

    /**
     * @param $xml
     *
     * @return array
     */
    protected function parseXml($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * @param array $arr
     *
     * @return string
     */
    protected function generateXml(array $arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            $xml .= is_numeric($val) ? "<{$key}>{$val}</{$key}>" : "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * @param $url
     * @param array $payload
     * @param null  $cert
     * @param null  $sslKey
     *
     * @return array
     */
    protected function request($url, array $payload, $cert = null, $sslKey = null)
    {
        $options = [];
        if (!is_null($cert)) {
            $options = [
                CURLOPT_SSLCERTTYPE => 'PEM',
                CURLOPT_SSLCERT => file_get_contents($cert),
                CURLOPT_SSLKEYTYPE => 'PEM',
                CURLOPT_SSLKEY => file_get_contents($sslKey),
            ];
        }

        $response = (new Request('POST', $url))->withOptions($options)->send($this->generateXml($payload));

        if (!$response->isSuccessful()) {
            throw new GatewayException('Wechat Gateway Error.', (string) $response->getBody());
        }

        $result = $this->parseXml((string) $response->getBody());

        if (isset($result['err_code']) || 'FAIL' === $result['return_code']) {
            throw new GatewayException(
                'Wechat Gateway Error: '.($result['return_msg'] ?? '').' '.($result['err_code_des'] ?? ''),
                $result
            );
        }

        return $result;
    }

    /**
     * @param $amount
     *
     * @return int
     */
    protected function formatAmount($amount)
    {
        return (int) ($amount * 1000 / 10);
    }

    /**
     * @param $status
     *
     * @return string
     */
    protected function formatTradeStatus($status)
    {
        switch ($status) {
            case 'NOTPAY':
            case 'USERPAYING':
                return 'created';
            case 'PAYERROR':
            case 'CLOSED':
                return 'closed';
            case 'REFUND':
            case 'REVOKED':
                return 'refund';
            default:
                return 'paid';
        }
    }
}
