<?php

require './weChat.class.php';
define('APPID', 'wxb694c94d706ab778');
define('APPSECRET', '053c237f1b3f4682d778685c7f341e58');
define('TONEN', 'token');
$wechat = new WeChat(APPID, APPSECRET,TOKEN);

// 获取access_token 
$access_token = $wechat->getAccessToken();
var_dump($access_token);

//$wechat->getQRCode(12344,'./Chrysanthemum.jpg',WeChat::QRCODE_TYPE_TEMP);
//$wechat->getQRCodeTicket(1234, 1);
//$wechat->getQRCodeTicket('http://php.itcast.cn/', 3);
