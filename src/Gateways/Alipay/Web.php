<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Alipay;

use Avengers\Jarvis\Requests\Charge;

class Web extends AbstractAlipayGateway
{
    protected function getChargeMethod()
    {
        return 'alipay.trade.page.pay';
    }

    protected function prepareCharge(Charge $form)
    {
        return [
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ];
    }
}
