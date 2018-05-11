<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-01
 */

namespace Avengers\Jarvis\Responses;


use Avengers\Jarvis\Utils\AbstractOption;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Close
 * @package Avengers\Jarvis\Responses
 */
class Close extends AbstractOption
{
    /**
     * @param OptionsResolver $resolver
     */
    protected function configureResolver(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'order_id' => '',
                'trade_sn' => '',
            ]
        );
    }
}
