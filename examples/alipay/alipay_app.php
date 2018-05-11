<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2018
 *
 * @see      https://www.github.com/janhuang
 * @see      http://www.fast-d.cn/
 */

use Avengers\Jarvis\Jarvis;

include __DIR__ . '/../../vendor/autoload.php';

$config = include __DIR__.'/config.php';

$jarvis = new Jarvis(Jarvis::JARVIS_ALIPAY_APP, $config);

$jarvis->enableDebug();

$form = $jarvis->charge([
    'order_id' => '151627101400000071',
    'subject' => 'testing',
    'amount' => '0.01',
    'currency' => 'CNY',
    'description' => 'testing description',
    'return_url' => 'https://www.baidu.com',
//    'expired_at' => '2018-06-23 19:00:00',
]);

echo '<pre>';
print_r($form);
