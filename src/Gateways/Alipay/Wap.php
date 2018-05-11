<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Alipay;

use Avengers\Jarvis\Requests\Charge;

class Wap extends AbstractAlipayGateway
{
    protected function getChargeMethod()
    {
        return 'alipay.trade.wap.pay';
    }

    protected function prepareCharge(Charge $form)
    {
        return [
            'product_code' => 'QUICK_WAP_WAY',
        ];
    }
}
