<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Requests;

use Avengers\Jarvis\Utils\AbstractOption;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Charge extends AbstractOption
{
    /**
     * @param OptionsResolver $resolver
     */
    protected function configureResolver(OptionsResolver $resolver)
    {
        $resolver->setRequired(
            [
                'order_id',
                'subject',
                'amount',
                'currency',
                'description',
            ]
        );
        $resolver->setDefaults(
            [
                'user_ip' => '127.0.0.1',
                'return_url' => '',
                'show_url' => '',
                'body' => '',
                'expired_at' => '',
                'created_at' => '',
            ]
        );
    }
}
