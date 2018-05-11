<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Alipay;

class Qr extends AbstractAlipayGateway
{
    protected function getChargeMethod()
    {
        return 'alipay.trade.precreate';
    }

    protected function doCharge(array $payload)
    {
        $response = $this->request($payload);

        return [
            'charge_url' => $response['qr_code'],
        ];
    }
}
