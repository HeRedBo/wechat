<?php
/**
 * 微信公众平台操作类
 */
class WeChat 
{   
    private $_appid;
    private $_appsecret;
    private $_token; // 公众号请求开发者时需要标识

    const QRCODE_TYPE_TEMP = 1;
    const QRCODE_TYPE_LIMIT = 2;
    const QRCODE_TYPE_LIMIT_STR = 3;

    private $_msg_template = [
        // 获取消息模板
        'text' => '<xml>
					    <ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[%s]]></MsgType>
						<Content><![CDATA[%s]]></Content>
						<FuncFlag>0</FuncFlag>
					</xml>',

        'image' => '<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[image]]></MsgType>
                        <Image>
                            <MediaId><![CDATA[%s]]></MediaId>
                        </Image>
                    </xml>',

        'music' => '<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Music>
                            <Title><![CDATA[%s]]></Title>
                            <Description><![CDATA[%s]]></Description>
                            <MusicUrl><![CDATA[%s]]></MusicUrl>
                            <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
                        </Music>
                    </xml>',
        'news' => '<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[news]]></MsgType>
                    <ArticleCount>%s</ArticleCount>
                    <Articles>%s</Articles>
                  </xml>',// 新闻主体
        'news_item' => '<item>
                        <Title><![CDATA[%s]]></Title>
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                       </item>',//某个新闻模板
    ];

    /**
     * 构造函数
     * 
     * @param int $id appID;
     * @param string $secret 公众号秘钥
     */
    public function __construct($id, $secret, $token)
    {
        $this->_appid     = $id;
        $this->_appsecret = $secret;
        $this->_token     = $token;
    }

    /**
     * 第一次校验URL的合法性
     * @return string 
     */
    public function firstValid()
    {
        // 校验签名的合法性
        if($this->_checkSignature())
        {
            // 签名合法 告知微信公众号服务器
            echo $_GET['echostr'];
        }
    }

    /**
     * 校验签名
     * @return bool 
     */
    private function _checkSignature()
    {
        // 获取微信公众号平台请求的验证数据
        $signature = $_GET['signature']; //微信加密签名
        $timestamp = $_GET['timestamp']; //时间戳
        $nonce     = $_GET['nonce'];    //随机数
        // 将时间戳，随机字符串，token按照字母顺序排序并连接
        $tmp_arr = array($this->_token,$timestamp, $nonce);
        sort($tmp_arr, SORT_STRING); // 字典排序
        $tmp_str = implode($tmp_arr); // 数据连接
        $tmp_str = sha1($tmp_str);

        if($signature == $tmp_str)
            return true;
        else
            return false;
    }

    /**
     * 获取 access_token
     * @param  string $token_file 用来存储token的临时文件
     * @return string access_token  
     */
    private function _getAccessToken($token_file = './access_token')
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
     * 设置菜单
     * @param  array $menu 需要设置的菜单
     * @return [type]       [description]
     */
    public function menuSet($menu)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->_getAccessToken();
        $data = $menu;
        $result = $this->_requestPost($url, $data);
        $result_obj = json_decode($result);
        if ($result_obj->errcode == 0)
        {
            return true;
        } 
        else 
        {
            echo $result_obj->errmsg, '<br>';
            return false;
        }
    }

    /**
     * 菜单删除
     * @return bool 
     */
    public function menuDelete()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $this->_getAccessToken();
        $result = $this->_requestGet($url);
        $result_obj = json_decode($result);
        if ($result_obj->errcode == 0) 
            return true; 
        else 
            return false;
    }


    /**
     * 回复微信公众号消息
     * @return void 
     */
    public function responseMSG()
    {
        // 获取请求的时时的POST ： XML字符串
        // $_POST ，不是key/value型因此不能使用改竖数组
        $xml_str = $GLOBALS['HTTP_RAW_POST_DATA'];
        // 如果没有post 数据 则响应空字符串表示结束
        if(empty($xml_str))
        {
            die ('');
        }

        // 解析xml 字符串 利用simpleXML 
        libxml_disable_entity_loader(true);//禁止xml实体解析，防止xml注入
        $request_xml = simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA); //从字符串获取simpleXML对象

        // 判断该消息的类型通过元素 ： msgType
        switch ($request_xml->msgType) 
        {
            case 'event': // 事件类型
                // 判断具体时间的类型 （关注 取消 点击）
                $event = $request_xml->Event;
                if('subscribe' == $event)
                {
                    $this->_doSubscribe($request_xml);
                }
                else if('CLICK' == $event)
                {
                    // 菜单点击事件
                }
                else if('VIEW' == $event)
                {
                    // 链接跳转事件
                }
                break;
            case 'text': // 文本消息
                $this->_doText($request_xml);
                break;
            case 'image': // 图片消息
                $this->_doImage($request_xml);
                break;
            case 'voice':  // 语音消息
                //$this->_doVoice($request_xml);
                break;
            case 'shortvideo' : // 短视频消息
                // TODO
                break;
            case 'video' : // 视频消息
                // TODO
                break;
            case 'location': // 位置信息
                $this->_doLocation($request_xml);
                break;
            case 'link' : //链接消息
                //$this->_doLink(); // TODO
                break;
            default:
                # code...
                break;
        }
    }

    /**
     * 处理用户关注事件
     * @param  object $request_xml 事件信息对象
     * @return [type]              [description]
     */
    private function _doSubscribe($request_xml)
    {
        // 利用消息发送 完成关注用户打招呼功能！
        $content = '感谢你关注跆拳一身！';
        $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
    }

    /**
     * 处理文本消息
     * @param  object $request_xml 事件信息对象
     * @return [type]              [description]
     */
    private function _doText($request_xml)
    {
        // 获取处理文本消息的方法
        $content = $request_xml->Content;
        // 对内容进行判断 
        if('?' == $content || '？' == $content)
        {
            // 显示帮助信息
            $response_content = '请输入对应序号或名称，获取相应的资源'."\n".'[1]PHP'."\n".'[2]Java'."\n".'[3]C++';
            // 将处理好的数据回复给客户
            $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $response_content);
        }
        else if('1' == strtolower($content) || 'php' == strtolower($content))
        {
            $response_content = 'PHP工程师培训: ' . "\n" . 'http://php.itcast.cn/';
            // 将处理好的数据回复给客户
            $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $response_content);
        }
        else if ('2' == strtolower($content) || 'java' == strtolower($content))
        {
            $response_content = 'Java工程师培训: ' . "\n" . 'http://java.itcast.cn/';
            // 将处理好的数据回复给客户
            $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $response_content);
        }
        else if('3' == strtolower($content) || 'c++' == strtolower($content))
        {
             $response_content = 'C++工程师培训: ' . "\n" . 'http://c.itcast.cn/';
            // 将处理好的数据回复给客户
            $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $response_content);
        }
        else if('图片' == $content)
        {
            $id_list = [
                'eLrmGKbhf5kS86A9bqzkLS8-45sWvqBwUv4Q7XDd-oAds44Ad9hxq9h-ShmRQLyJ',
                '0Fnq-gYU8zDugqxjPNywkhW5KSHXT6DdF-NGovaPfKry8grmheEVdEkdeY8qjZ--'
            ];
            $rand_index = mt_rand(0, count($id_list) -1);
            // 随机抽取图片
            $this->_msgImage($request_xml->FromUserName, $request_xml->ToUserName, $id_list[$rand_index], true);
        }
        else if('音乐' == $content)
        {
            $music_url = 'http://weixin.fortheday.cn/dnaw.mp3';
            $hq_music_url = 'http://weixin.fortheday.cn/dnaw.mp3';
            $thumb_media_id = '0Fnq-gYU8zDugqxjPNywkhW5KSHXT6DdF-NGovaPfKry8grmheEVdEkdeY8qjZ--';
            $title = '等你爱我';
            $desc = '等你爱我-等到地老天荒';
            $this->_msgMusic($request_xml->FromUserName, $request_xml->ToUserName, $music_url, $hq_music_url, $thumb_media_id, $title, $desc);
        } 
        else if('新闻' == $content)
        {
            $item_list  = [
                ['title'=>'其实你该用母亲的方式回报母亲', 'desc'=>'母亲节快乐', 'picurl'=>'http://weixin.fortheday.cn/1.jpg', 'url'=>'http://www.soso.com/'],
                ['title'=>'母亲节宠爱不手软，黄金秒杀豪礼特惠值到爆', 'desc'=>'母亲节快乐', 'picurl'=>'http://weixin.fortheday.cn/2.jpg', 'url'=>'http://www.soso.com/'],
                ['title'=>'浅从财富管理视角看巴菲特思想', 'desc'=>'母亲节快乐', 'picurl'=>'http://weixin.fortheday.cn/3.jpg', 'url'=>'http://www.soso.com/'],
                ['title'=>'广邀好友打气，赢取万元旅游金', 'desc'=>'母亲节快乐', 'picurl'=>'http://weixin.fortheday.cn/4.jpg', 'url'=>'http://www.soso.com/']
            ];
            $this->_msgNews($request_xml->FromUserName, $request_xml->ToUserName, $item_list);
        } 
        else 
        {
            // 对接图灵机器人 响应给微信用户
            $url = "http://www.tuling123.com/openapi/api?key=db990a04ecf73788cb89f317742b45f8&info={$content}";
            // $str  = file_get_contents($url);
            // $json = json_decode($str);
            $response_content = $this->_requestGet($url, false);
            $response_data = json_decode($response_content);

            if($response_data->code == '100000')
            {
                $content = $response_data->text;
                $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
            }
            else if ($response_data->code == '302000')
            {
                // 新闻信息接口
                $this->_tuLingMsgNews($request_xml->FromUserName, $request_xml->ToUserName,$response_data->list);
            }
            else if($response_data->code =="200000")
            {
                //明星图片的接口 以文本的形式返回
                $content = $response_data->text .",请点击  <a href='$response_data->url'>打开页面</a>";
                $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
            }
            else if ($response_data->code =="308000")
            {
                // 菜谱信息
                $this->_tuLingCaiPuMsgNews($request_xml->FromUserName, $request_xml->ToUserName,$response_data->list);
            }
            else if($response_data->code == '305000')
            {
                //列车查询接口 使用图文信息返回
                $this->_tlLieCheMsgNews($request_xml->FromUserName, $request_xml->ToUserName,$response_data->list);
            }
        }
    }

    /**
     * 处理图片消息
     * @param  object $request_xml 事件信息对象
     * @return [type]  [description]
     */
    private function _doImage($request_xml)
    {
        $content = '你所上传的图片的Media_ID:' . $request_xml->MediaId."\n".'图片真的很漂亮';
        $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
    }

    private function _doLocation($request_xml)
    {
        $content = '你的坐标为,经度:'.$request_xml->Location_Y.',纬度:'.$request_xml->Location_X . "\n" . '你所在的位置为：' . $request_xml->Label;
        $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
    }

    /**
     * 
     * 回复文本消息
     * @param  string $to      目标用户ID
     * @param  string $from    来源用户ID
     * @param  string $content 内容
     * @return string          xml对象
     */
    public function _msgText($to, $from, $content)
    {
        $response = sprintf($this->_msg_template['text'], $to, $from, time(), $content);
        die($response);
    }
    
    /**
     * 发送图片信息
     * @param  string  $to    目标用户ID
     * @param  string  $from  来源用户ID
     * @param  void    $file  图片文件或者图片的media_id
     * @param  boolean $is_id 是否是media_id
     * @return [type]         [description]
     */
    private function _msgImage($to, $from, $file, $is_id = false)
    {
        if($is_id)
        {
            $media_id = $file;
        } 
        else
        {
            // 上传图片到微信公众号服务器 获取 mediaID
            $result_obj = $this->uploadTmp($file,'image');
            $media_id   = $result_obj->media_id;
        }

        $response =sprintf($this->_msg_template['image'], $to, $from, time(), $media_id);
        die($response);
    }

    /**
     * 发送音乐消息
     * @param  string  $to              目标用户ID
     * @param  string  $from            来源用户ID
     * @param  string $music_url       音乐链接
     * @param  string  $hq_music_url    高质量音乐链接
     * @param  string  $thumb_media_id  缩略图的媒体id
     * @param  string  $title           音乐标题
     * @param  string  $desc            音乐描述
     * @return [type]                 [description]
     */
    private function _msgMusic($to, $from, $music_url, $hq_music_url, $thumb_media_id, $title = '', $desc = '')
    {
        $response = sprintf($this->_msg_template['music'], $to, $from, time(), $title, $desc, $music_url, $hq_music_url, $thumb_media_id);
        die($response);
    }

    /**
     * 发送图文消息
     * @param  string  $to        目标用户ID
     * @param  string  $from      来源用户ID
     * @param  array $item_list   内容数组
     * @return [type]            [description]
     */
    private function _msgNews($to, $from, $item_list)
    {
        //拼凑文章部分
        $item_str = '';
        foreach ($item_list as $item) 
        {
            $item_str .= sprintf($this->_msg_template['news_item'], $item['title'], $item['desc'], $item['picurl'], $item['url']);
        }
        // 拼凑整体图文部分
        $response = sprintf($this->_msg_template['news'], $to, $from, time(), count($item_list), $item_str);
        die($response);
    }

    /**
     * 发送图灵机器人图文消息
     * @param  string  $to        目标用户ID
     * @param  string  $from      来源用户ID
     * @param  array $item_list   内容数组
     * @return [type]            [description]
     */
    private function _tuLingMsgNews($to, $from, $item_list)
    {
        //拼凑文章部分
        $item_str = '';
        foreach ($item_list as $item) 
        {
            $item_str .= sprintf($this->_msg_template['news_item'], $item['article'], $item['source'], $item['icon'], $item['detailurl']);
        }
         // 拼凑整体图文部分
        $response = sprintf($this->_msg_template['news'], $to, $from, time(), count($item_list), $item_str);
        die($response);
    }

    /**
     * 发送图灵机器人菜谱消息
     * @param  string  $to        目标用户ID
     * @param  string  $from      来源用户ID
     * @param  array $item_list   内容数组
     * @return [type]            [description]
     */
    private function _tuLingCaiPuMsgNews($to, $from, $item_list)
    {
        $count = count($item_list) > 5 ? 5 : count($item_list);
        //拼凑文章部分
        $item_str = '';
        foreach ($item_list as $k => $item) 
        {
            if($k >= $count)  break;
            $arr = explode("|", $item->info);
            $title  = $item->name." \n主料 : " . $arr[0] ."\n辅料 : ".$arr[1];
            $desc   = $item->info;
            $picUrl = $item->icon;
            $url    = $item->detailurl;
            $item_str .= sprintf($this->_msg_template['news_item'], $title, $desc, $picUrl, $url);
        }
         // 拼凑整体图文部分
        $response = sprintf($this->_msg_template['news'], $to, $from, time(), $count, $item_str);
        die($response);
    }

    /**
     * 发送图灵机器人列车班次消息
     * @param  string  $to        目标用户ID
     * @param  string  $from      来源用户ID
     * @param  array $item_list   内容数组
     * @return [type]            [description]
     */
    private function _tlLieCheMsgNews($to, $from, $item_list)
    {
        $count = count($item_list) > 5 ? 5 : count($item_list);

        //拼凑文章部分
        $item_str = '';
        foreach ($item_list as $k => $item) 
        {
            if($k >= $count)  break;
            $title  = '车次:'. $item->trainnum ."\n出发-到达:". $item->start. '(始) --> '.$item->terminal . "\n发时-到时:".$item->starttime." - ".$item->endtime."\n";
            $desc   = "列车班次:".$item->trainnum ."\n". $item->start." --> ".$item->terminal."\n".$item->starttime.' -- '.$item->endtime."\n";
            $picUrl = $item->icon;
            $url    = $item->detailurl;
            $item_str .= sprintf($this->_msg_template['news_item'], $title, $desc, $picUrl, $url);
        }
        // 拼凑整体图文部分
        $response = sprintf($this->_msg_template['news'], $to, $from, time(), $count, $item_str);
        die($response);
    }

    /**
     * 上传临时素材
     * @param  void $file 需要上传的文件
     * @param  string $type 文件类型
     * @return [type]       [description]
     */
    public function uploadTmp($file, $type)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$this->_getAccessToken() .'&type='.$type;
        $data['media'] = '@'.$file;
        $result = $this->_requestPost($url, $data);
        $result_obj = json_decode($result);
        return $result_obj;
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
        $access_token = $this->_getAccessToken();
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
            case self::QRCODE_TYPE_LIMIT_STR:
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