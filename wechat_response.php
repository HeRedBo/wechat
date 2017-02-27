<?php

header('Content-Type: text/html; charset=utf8');

define('APPID','wxb694c94d706ab778');
define('APPSECRET', '053c237f1b3f4682d778685c7f341e58');
define('TOKEN', 'token');
$wechat = new WeChat(APPID, APPSECRET, TOKEN);
// 第一次验证：
// $wechat->firstValid();
//
// 处理威信公众平台的的消息（事件）
$wechat->responseMSG();