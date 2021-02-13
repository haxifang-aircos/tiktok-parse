<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TikTokController extends Controller
{
    public function parse(Request $request)
    {
        $shareText = $request->input('share_link');

        $url = self::GetURL($shareText);
        $url = $this->getCurl($url);

        preg_match('/video\/([0-9]+)\//i', $url, $matches);

        if (!isset($matches[1])) {
            dd('获取抖音 id 失败');
        }

        $result = $this->video_url($matches[1]);
        return $result;
    }

    /**
     * 获取分享链接中的播放地址
     */
    public static function GetURL($content)
    {
        $regex = '@(?i)\b((?:[a-z][\w-]+:(?:/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))@';
        if (preg_match($regex, $content, $match)) {
            return $match[0];
        }
    }

    /**
     * 发起请求
     */
    private function getCurl($url, $options = [], $foll = 0)
    {
        //初始化
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //访问的url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //完全静默
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //忽略https
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //忽略https
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([self::getRandomUserAgent()], $options)); //UA
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $foll); //默认为$foll=0,大概意思就是对照模块网页访问的禁止301 302 跳转。
        $output = curl_exec($ch); //获取内容
        curl_close($ch); //关闭
        return $output; //返回
    }

    /**
     * 获取抖音接口视频信息
     * @param $id
     * @return array
     */
    private function video_url($dyid)
    {
        $getApi = 'https://www.iesdouyin.com/web/api/v2/aweme/iteminfo/?item_ids=' . $dyid;
        $data = $this->getCurl($getApi);
        $json = json_decode($data, true);
        //视频描述
        $video = [];
        $video['code'] = 1;
        $playUrl = null;
        foreach ($json['item_list'] as $k => $v) {
            //ID
            $list['aweme_id'] = $v['statistics']['aweme_id'];
            //视频描述
            $list['desc'] = $v['desc'];
            //评论数
            $list['comment_count'] = $v['statistics']['comment_count'];
            //点赞数
            $list['digg_count'] = $v['statistics']['digg_count'];
            //无水印URL
            $playUrl = $list['play_url'] = $this->Url($v['video']['play_addr']['uri']);
            $video[] = $list;
        }
        return [
            'raw' => $json,
            'video' => [
                'info' => $video,
                'play_url' => urldecode($playUrl),
            ],
        ];
    }

    /**
     * 获取重定向视频地址
     * 
     * @param $videoId
     * @return string
     */
    private function Url($videoId)
    {
        $str = $this->getCurl("https://aweme.snssdk.com/aweme/v1/play/?video_id=" . $videoId . "&line=0", [
            'Referer' => "https://www.iesdouyin.com",
            'Host' => "www.iesdouyin.com",
        ], 0);
        preg_match('#<a href="(.*?)">#', $str, $data);
        $video = explode("//", $data[1]);
        return isset($video[1]) ? 'https://' . $video[1] : '解析失败';
    }

    public static function getRandomUserAgent()
    {
        $agents = [
            'Mozilla/5.0 (Linux; U; Android 4.0.3; ko-kr; LG-L160L Build/IML74K) AppleWebkit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30',
            'Mozilla/5.0 (Linux; U; Android 4.0.3; de-ch; HTC Sensation Build/IML74K) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30',
            'Mozilla/5.0 (Linux; U; Android 2.3.5; zh-cn; HTC_IncredibleS_S710e Build/GRJ90) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
            'Mozilla/5.0 (Linux; U; Android 2.3.5; en-us; HTC Vision Build/GRI40) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
            'Mozilla/5.0 (Linux; U; Android 2.3.4; fr-fr; HTC Desire Build/GRJ22) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
        ];

        return 'user-agent: ' . $agents[array_rand($agents)];
    }
}
