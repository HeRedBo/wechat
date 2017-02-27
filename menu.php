<?php
require './weChat.class.php';

define('APPID','wxb694c94d706ab778');
define('APPSECRET', '053c237f1b3f4682d778685c7f341e58');
$wechat = new WeChat(APPID, APPSECRET);

// $result = $wechat->menuDelete();
// var_dump($result);

$menu = <<<JSON
{
    "button": [
        {
            "name": "扫码",
            "sub_button": [
                {
                    "type": "scancode_waitmsg",
                    "name": "扫码带提示",
                    "key": "scancode_waitmsg",
                    "sub_button": [ ]
                },
                {
                    "type": "scancode_push",
                    "name": "扫码推事件",
                    "key": "scancode_push",
                    "sub_button": [ ]
                }
            ]
        },
        {
            "name": "发图",
            "sub_button": [
                {
                    "type": "pic_sysphoto",
                    "name": "系统拍照发图",
                    "key": "pic_sysphoto",
                   "sub_button": [ ]
                 },
                {
                    "type": "pic_photo_or_album",
                    "name": "拍照或者相册发图",
                    "key": "pic_photo_or_album",
                    "sub_button": [ ]
                },
                {
                    "type": "pic_weixin",
                    "name": "微信相册发图",
                    "key": "pic_weixin",
                    "sub_button": [ ]
                }
            ]
        },
        {
            "name": "快捷操作",
            "sub_button" : [
                {
                    "name": "地理位置",
                    "type": "location_select",
                    "key": "location_select"
                },
                {
                    "name": "普通点击",
                    "type": "click",
                    "key": "click"
                },
                {
                    "name": "查看URL",
                    "type": "view",
                    "url" : "http://www.soso.com/"
                },
            ]
        },
    ]
}
JSON;
$result = $wechat->menuSet($menu);
var_dump($result);