<?php

ini_set('display_errors', '1');
require './weChat.class.php';
define('APPID','wxb694c94d706ab778');
define('APPSECRET', '053c237f1b3f4682d778685c7f341e58');
define('TOKEN', 'token');
$wechat = new WeChat(APPID, APPSECRET, TOKEN);
$file = './Chrysanthemum.jpg';
$result = $wechat->uploadTmp($file, 'image');
var_export($result);