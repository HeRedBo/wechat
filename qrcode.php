<?php

require_once  './weChat.class.php';

define('APPID', 'wxb694c94d706ab778');
define('APPSECRET', '053c237f1b3f4682d778685c7f341e58');
define('TONEN', 'token');

$wechat = new WeChat(APPID, APPSECRET, TOKEN);
$wechat -> getQRCode(42, NUll);




