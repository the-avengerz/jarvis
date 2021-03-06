<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-03
 */

namespace Avengers\Jarvis\Gateways\Alipay;

use Avengers\Jarvis\Requests\Charge;

class App extends AbstractAlipayGateway
{
    /**
     * @return string
     */
    protected function getChargeMethod()
    {
        return 'alipay.trade.app.pay';
    }

    protected function prepareCharge(Charge $form)
    {
        return [
            'product_code' => 'QUICK_MSECURITY_PAY',
        ];
    }

    protected function doCharge(array $payload)
    {
        return [
            'charge_url' => '',
            'parameters' => [
                'string' => http_build_query($payload),
            ],
        ];
    }
}
