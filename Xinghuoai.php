<?php


use DateTime;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\Factory;
use React\Socket\Connector as ReactConnector;

require __DIR__ . '/vendor/autoload.php';

class Xinghuoai
{
    public function index()
    {
        //输入配置信息
        $res            = NULL;
        $appid          = "appid";
        $apikey         = "apikey";
        $APISecret      = "APISecret";
        $gpt_url        = "wss://spark-api.xf-yun.com/v1.1/chat";
        
        $url_components = parse_url($gpt_url);
        // 生成RFC1123格式的时间戳
        $date = DateTime::createFromFormat('U', time())->format('D, d M Y H:i:s T');

        // 拼接字符串
        $signature_origin = "host: " . $url_components['host'] . "\n";
        $signature_origin .= "date: " . $date . "\n";
        $signature_origin .= "GET " . $url_components['path'] . " HTTP/1.1";

        // 进行hmac-sha256进行加密
        $signature_sha = hash_hmac('sha256', utf8_encode($signature_origin), utf8_encode($APISecret), true);
        $signature_sha_base64 = base64_encode($signature_sha);
        $authorization_origin = 'api_key="' . $apikey . '", algorithm="hmac-sha256", headers="host date request-line", signature="' . $signature_sha_base64 . '"';
        $authorization = base64_encode(utf8_encode($authorization_origin));

        // 将请求的鉴权参数组合为字典
        $v = [
            "authorization" => $authorization,
            "date"          => $date,
            "host"          => $url_components['host']
        ];
        // 拼接鉴权参数，生成url
        $url = $gpt_url . '?' . http_build_query($v);

        $data = [
            "header" => [
                "app_id" => $appid,
                "uid"    => "pella"
            ],
            "parameter" => [
                "chat" => [
                    "domain"      => "general",
                    "temperature" => 0.5,
                    "max_tokens"  => 1024,
                ]
            ],
            "payload" => [
                "message" => [
                    "text" => [["role" => "user", "content" => "hello world"]]
                ]
            ]
        ];

        $loop = Factory::create();
        $connector = new Connector($loop, new ReactConnector($loop));

        $connector($url)->then(function (WebSocket $conn) use ($data, &$res) {
            $conn->on('message', function ($msg) use ($conn, &$res) {
                $tmp = json_decode($msg, true);
                $res = $tmp;
                $conn->close();
                //只获取文本信息
                // if ($tmp['header']['status'] == 1 || $tmp['header']['status'] == 0) {
                //     $res .= $tmp['payload']['choices']['text'][0]['content'];
                // } else if ($tmp['header']['status'] == 2) {
                //     $conn->close();
                // }
            });

            $conn->send(json_encode($data));
        }, function ($e) {
            echo "Could not connect: {$e->getMessage()}\n";
        });

        $loop->run();

        return $res;
    }
}