<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Gateways\Wechat;

use Avengers\Jarvis\Requests\Charge;

class Qr extends AbstractWechatGateway
{
    /**
     * @param Charge $form
     *
     * @return array
     */
    protected function prepareCharge(Charge $form)
    {
        return [];
    }

    protected function doCharge(array $response, Charge $form)
    {
        return [
            'charge_url' => $response['code_url'],
            'parameters' => [
                'prepay_id' => $response['prepay_id'],
            ],
        ];
    }

    protected function getTradeType()
    {
        return 'NATIVE';
    }
}
