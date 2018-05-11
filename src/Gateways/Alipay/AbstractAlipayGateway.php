<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Alipay;


use Avengers\Jarvis\Utils\AbstractOption;
use FastD\Http\Request;
use Avengers\Jarvis\Exception\GatewayException;
use Avengers\Jarvis\Exception\GatewayMethodNotSupportException;
use Avengers\Jarvis\AbstractGateway;
use Avengers\Jarvis\Requests\Charge;
use Avengers\Jarvis\Requests\Close;
use Avengers\Jarvis\Requests\Query;
use Avengers\Jarvis\Requests\Refund;
use InvalidArgumentException;

/**
 * Class AbstractAlipayGateway
 * @package Avengers\Jarvis\Gateways\Alipay
 */
abstract class AbstractAlipayGateway extends AbstractGateway
{
    const PRODUCT_GATEWAY = 'https://openapi.alipay.com/gateway.do';
    const SANDBOX_GATEWAY = 'https://openapi.alipaydev.com/gateway.do';

    /**
     * @param Charge $form
     * @return array
     */
    public function charge(Charge $form)
    {
        $content = array_merge(
            [
                'out_trade_no' => $form->get('order_id'),
                'total_amount' => bcadd($form->get('amount'), 0, 2),
                'subject' => $form->get('subject'),
                'body' => $form->get('description'),
            ],
            $this->prepareCharge($form),
            $form->get('extras')
        );

        if ($form->get('expired_at')) {
            if (($expiredAt = $form->get('expired_at')) <= time() + 60) {
                throw new InvalidArgumentException('charge must expire after 1 minutes');
            }
            $content['timeout_express'] = (int)(($expiredAt - time()) / 60).'m';
        }

        $payload = $this->createPayload(
            $this->getChargeMethod(),
            $content,
            $this->config->get('notify_url'),
            $form->get('return_url')
        );

        unset($content);

        return $this->doCharge($payload);
    }

    /**
     * @param Refund $form
     *
     * @return array
     */
    public function refund(Refund $form)
    {
        $payload = $this->createPayload(
            'alipay.trade.refund',
            array_merge(
                [
                    'out_trade_no' => $form->get('order_id'),
                    'refund_amount' => $form->get('refund_amount'),
                    'refund_reason' => $form->get('reason'),
                    'out_request_no' => $form->get('refund_id'),
                ],
                $form->get('extras')
            )
        );

        $response = $this->request($payload);

        if ('Y' !== $response['fund_change']) {
            throw new GatewayException('Alipay Refund Failed', $response);
        }

        return [
            'refund_sn' => $response['trade_no'],
            'refund_amount' => $response['refund_fee'],
            'raw' => $response,
        ];
    }

    /**
     * @param Close $form
     *
     * @return array
     */
    public function close(Close $form)
    {
        $payload = $this->createPayload(
            'alipay.trade.close',
            [
                'out_trade_no' => $form->get('order_id'),
            ],
            $this->config->get('notify_url')
        );

        $this->request($payload);

        return [];
    }

    /**
     * @param Query $form
     *
     * @return array
     */
    public function query(Query $form)
    {
        $payload = $this->createPayload(
            'alipay.trade.query',
            [
                'out_trade_no' => $form->get('order_id'),
                'trade_no' => $form->get('trade_sn'),
            ]
        );

        $result = $this->request($payload);

        return [
            'order_id' => $result['out_trade_no'],
            'status' => $this->formatTradeStatus($result['trade_status']),
            'trade_sn' => $result['trade_no'],
            'buyer_identifiable_id' => $result['buyer_user_id'],
            'amount' => $result['total_amount'],
            'buyer_name' => $result['buyer_logon_id'],
            // 支付宝打款时间, 作为付款时间有些争议
            'paid_at' => isset($result['send_pay_date']) ? strtotime($result['send_pay_date']) : 0,
            'raw' => $result,
        ];
    }

    public function chargeNotify(array $receives)
    {
        return [
            'order_id' => $receives['out_trade_no'],
            'status' => $this->formatTradeStatus($receives['trade_status']),
            'trade_sn' => $receives['trade_no'],
            'buyer_identifiable_id' => $receives['buyer_id'],
            'amount' => isset($receives['receipt_amount']) ? $receives['receipt_amount'] : 0,
            'buyer_name' => '',
            'paid_at' => (isset($receives['gmt_payment']) ? strtotime($receives['gmt_payment']) : 0),
            'raw' => $receives,
        ];
    }

    /**
     * @param array $receives
     */
    public function refundNotify(array $receives)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * @param $receives
     *
     * @return array
     */
    public function closeNotify(array $receives)
    {
        throw new GatewayMethodNotSupportException();
    }

    /**
     * @param $receives
     *
     * @return bool
     */
    public function verify($receives)
    {
        $sign = $receives['sign'];
        unset($receives['sign'], $receives['sign_type']);
        ksort($receives);

        $publicKey = ('.pem' === substr($this->config->get('public_key'), -4)
            ? openssl_get_publickey("file://{$this->config->get('public_key')}")
            : $this->config->get('public_key'));

        if (is_string($publicKey)) {
            $publicKey = "-----BEGIN RSA PUBLIC KEY-----\n".
                wordwrap($publicKey, 64, "\n", true).
                "\n-----END RSA PUBLIC KEY-----";
        }

        return 1 === openssl_verify(
                urldecode(
                    http_build_query(
                        array_filter(
                            $receives,
                            function ($value) {
                                return '' !== $value;
                            }
                        )
                    )
                ),
                base64_decode($sign),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );
    }

    /**
     * @return string
     */
    public function success()
    {
        return 'success';
    }

    /**
     * @return string
     */
    public function fail()
    {
        return 'fail';
    }

    public function receiveNotificationFromRequest()
    {
        return $_POST;
    }

    /**
     * @param $receives
     *
     * @return array
     */
    public function convertNotificationToArray($receives)
    {
        return $receives;
    }

    /**
     * @param string $method
     * @param array $content
     * @param string $notifyUrl
     * @param string $returnUrl
     *
     * @return array
     */
    protected function createPayload($method, array $content, $notifyUrl = '', $returnUrl = '')
    {
        $parameters = [
            'app_id' => $this->config->get('app_id'),
            'method' => $method,
            'format' => 'JSON',
            'return_url' => $returnUrl,
            'charset' => 'utf8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $notifyUrl,
            'biz_content' => json_encode($content),
        ];
        $parameters['sign'] = $this->sign($parameters);

        return $parameters;
    }

    /**
     * @return string
     */
    abstract protected function getChargeMethod();

    /**
     * @param array $payload
     *
     * @return array
     */
    protected function doCharge(array $payload)
    {
        return [
            'charge_url' => $this->gateway_url.'?'.http_build_query($payload),
            'parameters' => $payload,
        ];
    }

    /**
     * @param Charge $form
     *
     * @return array
     */
    protected function prepareCharge(Charge $form)
    {
        return [];
    }

    /**
     * @param array $parameters
     *
     * @return array
     */
    protected function request(array $parameters)
    {
        $response = (new Request('POST', $this->gateway_url))->send($parameters);

        if ( ! $response->isSuccessful()) {
            throw new GatewayException('Alipay Gateway Error.', $response);
        }

        $result = json_decode(mb_convert_encoding($response->getBody(), 'utf-8', 'gb2312'), true);

        $index = str_replace('.', '_', $parameters['method']).'_response';

        if ('10000' !== $result[$index]['code']) {
            throw new GatewayException(
                'Alipay Gateway Error: '.$result[$index]['msg']
                .'. sub_code: '.$result[$index]['sub_code']
                .'. sub_msg: '.$result[$index]['sub_msg'],
                $result[$index]['code']
            );
        }

        return $result[$index];
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
        $sign = '';

        $privateKey = '.pem' === substr($this->config->get('private_key'), -4)
            ? openssl_get_privatekey("file://{$this->config->get('private_key')}")
            : $this->config->get('private_key');

        if (is_string($privateKey)) {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----".PHP_EOL.
                wordwrap($privateKey, 64, PHP_EOL, true).PHP_EOL.
                "-----END RSA PRIVATE KEY-----";
        }

        openssl_sign(
            urldecode(
                http_build_query(
                    array_filter(
                        $parameters,
                        function ($value) {
                            return '' !== $value;
                        }
                    )
                )
            ),
            $sign,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        return base64_encode($sign);
    }

    /**
     * @param $status
     *
     * @return string
     */
    protected function formatTradeStatus($status)
    {
        $map = [
            'TRADE_SUCCESS' => 'paid',
            'WAIT_BUYER_PAY' => 'created',
            'TRADE_CLOSED' => 'closed',
        ];

        return $map[$status];
    }
}
