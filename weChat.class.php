<?php

/**
 * 微信公众平台操作类
 */
class WeChat 
{   

    private $_appid;
    private $_appsecret;

    const QRCODE_TYPE_TEMP = 1;
    const QRCODE_TYPE_LIMIT = 2;
    const QRCODE_TYPE_LIMIT_STR = 3;


    /**
     * 构造函数
     * 
     * @param int $id appID;
     * @param striing $secret 公众号秘钥
     */
    public function __construct($id, $secret)
    {
        $this->_appid = $id;
        $this->_appsecret = $secret;
    }

    /**
     * 获取access_teken
     * @param  string $token_file 用来存储token的临时文件
     * @return string access_token  
     */
    public function getAccessToke($token_file = './access_token') 
    {
        $life_time = 7200; // 两天后过去 微信公众限制
        if(file_exists($token_file) && time() - filemtime($token_file) < $life_time)
        {
            // 存在有效的 access_token 
            return file_get_contents($token_file);
        }
        // 目标url 
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->_appid}&secret={$this->_appsecret}";
        // 发送get 请求 获取access_token;
        $result = $this->_requestGet($url);
        if(!$result)
        {
            return false;
        }
        // 存在响应体内容结果
        $result_obj = json_decode($result);
        // 文件写入
        file_put_contents($token_file, $result_obj->access_token);
        return $result_obj->access_token;
    }

    /**
     * 获取二维码链接
     * @param  string|int  $content qucode内容标识
     * @param  string      $file    存储文件的地址，如果为null表示为直接输出
     * @param  int         $type    类型
     * @param  integer     $expire  如果是临时，表示其有效期
     * @return 
     */
    public function getQRCode($content, $file = NULL, $type, $expire = 604800)
    {
        // 获取ticket 
        $ticket = $this->_getQRCodeTicket($content, $type = 2, $expire = 604800);
        $url    = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=$ticket";
        $result = $this->_requestGet($url);
        if($file)
        {
            file_put_contents($file, $result);
        }
        else
        {
            header('Content-Type: image/jpeg');
            echo $result;
        }
    }

    public function _getQRCodeTicket($content, $type = 2, $expire = 604800)
    {
        $access_token = $this->getAccessToke();
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_token";
        $type_list = [
            self::QRCODE_TYPE_TEMP      => 'QR_SCENE',
            self::QRCODE_TYPE_LIMIT     => 'QR_LIMT_SCENT',
            self::QRCODE_TYPE_LIMIT_STR => 'QRCODE_TYPE_LIMIT_STR'
        ];

        $action_name = $type_list[$type];
        $data_arr = [];
        switch ($type) 
        {
            case self::QRCODE_TYPE_TEMP:
                #  {"expire_seconds": 604800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": 123}}}
                $data_arr['expire_seconds'] = $expire;
                $data_arr['action_name']    = $action_name;
                $data_arr['action_info']['scene']['scene_id'] = $content;
                break;
            case self::QRCODE_TYPE_LIMIT:
                break;
            case sele::QRCODE_TYPE_LIMIT_STR:
                $data_arr['action_name'] = $action_name;
                $data_arr['action_info']['scene']['scene_id'] = $content;
                break;
            default:
                # code...
                break;
        }

        $data = json_encode($data_arr);
        $result = $this->_requestPost($url, $data);
        if(!$result)
        {
            return false;
        }
        // 处理响应数据
        $result_obj = json_decode($result);
        return $result_obj->ticket;
    }

    /**
     * 发送GET请求的方法
     * @param  string  $url URL
     * @param  boolean $ssl 是否https协议
     * @return string  响应主体Content
     */
    private function _requestGet($url, $ssl= true)
    {
        // curl 完成
        $curl = curl_init();
        // 设置curl 选项
        curl_setopt($curl, CURLOPT_URL, $url);
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0 FirePHP/0.7.4';
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);  //referer头，请求来源

        if($ssl)
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 禁止后curl将终止从服务端验证
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);     //检查服务器SSL证书中是否存在一个公钥 （common name）
        }
        curl_setopt($curl, CURLOPT_HEADER, false); //是否处理响应头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // curl_exec 是否返回响应结果

        // 发出请求
        $response = curl_exec($curl);
        if(false === $response)
        {
            echo "<br>", curl_exec($curl),'<br/>';
            return false;
        }
        return $response;
    }

    /**
     * 发送POST 请求的方法
     * 
     * @param  string  $url URL
     * @param  string  $data 格式化后的请求数据
     * @param  boolean $ssl  是否https协议
     * @return string  响应主体Content
     */
    private function _requestPost($url, $data, $ssl=true)
    {
        // curl 完成
        $curl = curl_init();
        // 设置curl 选项
        curl_setopt($curl, CURLOPT_URL, $url);
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0 FirePHP/0.7.4';
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);  //referer头，请求来源

        if($ssl)
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 禁止后curl将终止从服务端验证
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);     //检查服务器SSL证书中是否存在一个公钥 （common name）
        }
        // 处理post 相关选项
        curl_setopt($curl, CURLOPT_POST, true); // 是否为post 请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // 处理请求数据

        curl_setopt($curl, CURLOPT_HEADER, false); //是否处理响应头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // curl_exec 是否返回响应结果

        // 发出请求
        $response = curl_exec($curl);
        if(false === $response)
        {
            echo "<br>", curl_exec($curl),'<br/>';
            return false;
        }
        return $response;
    }

}