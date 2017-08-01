<?php
namespace spider;

use Yurun\Until\HttpRequest;
use PhpQuery\PhpQuery;
use SuperClosure\Serializer;

class Spider{
    public static $client;
    public static $request;
    public $agent = [
        'pc'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
        'baidu'=>'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)',
        'mobile'=>'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Mobile Safari/537.36',
        ];
    public function __construct()
    {
        if (!isset(self::$client)){
            self::$client = HttpRequest::newSession();
        }
        self::$client->header("X_FORWARDED_FOR",'220.181.108.'.rand(1,255));
    }
    public function fetch($config = array())
    {
        if (!isset($config['url'])) return false;

        $urlinfo = parse_url($config['url']);

        self::$client->ua(isset($config['client']['agent']) ? (isset($this->agent[$config['client']['agent']]) ?$this->agent[$config['client']['agent']] :$config['client']['agent']) : $this->agent['baidu']);

        self::$client->header("Host",$urlinfo['host']);

        $method = isset($config['client']['method']) ? $config['client']['method'] : 'GET';

        $requestBody = isset($config['client']['requestBody']) ? $config['client']['requestBody'] : [];

        if (isset($config['client']['referer']))
        self::$client->referer($config['client']['referer']);

        if (isset($config['client']['cookies']))
        self::$client->cookies($config['client']['cookies']);

        if (isset($config['client']['rawheaders']))
            self::$client->headers($config['client']['rawheaders']);

        if (isset($config['filename']))
        {
            $filename = isset($config['filename']) ? $config['filename'] : sprintf('data/wk/%d/%d/%d',date("Ym"),date("d"),rand(100000000,999999999));
            if (!file_exists(dirname(ROOT_PATH."public/".$filename)))
                mkdir(dirname(ROOT_PATH."public/".$filename),0766,true);
            $res = self::$client->send($config['url'],$requestBody,$method);
            if ($res->body){
                file_put_contents(ROOT_PATH."public/".$filename,$res->body);
            }
            return $res;
        }else{
            $res = self::$client->send($config['url'],$requestBody,$method);
        }
        $dom = (isset($res->headers['Content-Encoding']) && 'gzip' == $res->headers['Content-Encoding']) ? @gzinflate(substr($res->body,10)) : $res->body;
        $rs = [
            'response' => [
                'referer' => $config['url'],
                'cookies' => $res->cookies
            ]
        ];
        $pq = '\PhpQuery\pq';
        foreach ($config['fields'] as $field){
            if (isset($field['selector'])){
                PhpQuery::newDocumentHTML($dom);
                $dom = $pq($field['selector']);
            }
            else{
                PhpQuery::newDocumentHTML();
            }
            if (isset($field['callback'])){
               $callback = self::unserialize($field['callback']);
                try{
                    $rs[$field['name']] = $callback($dom,$pq);
                }catch (\think\Exception $e){
                    throw $e;
                }

           }else{
               $rs[$field['name']] = $pq($dom)->html();
           }
        }
        return $rs;
    }
    /**
     * @param array $config
     */
    public function setCookie()
    {

    }

    public function setDoc($doc = '')
    {
        PhpQuery::newDocumentHTML($doc);
    }
    public static function serialize($callback){
        $serializer = new Serializer();
        return $serializer->serialize($callback);
    }
    public static function unserialize($callback){
        $serializer = new Serializer();
        return $serializer->unserialize($callback);
    }

    public static function saveFile($filename,$content)
    {
        if (!file_exists(dirname(ROOT_PATH."public/".$filename)))
            mkdir(dirname(ROOT_PATH."public/".$filename),0766,true);
        file_put_contents(ROOT_PATH."public/".$filename,$content);
    }

    /**
     * @param $xml
     * @param $imgurl
     * @return string
     * @throws \think\Exception
     */
    public static function docXml($xml, $imgurl)
    {
        $html = '';
        foreach ($xml as $x){
            $x = (array) $x;
            if (!isset($x['c'])){
                return $html;
            }
            if ($x['t'] == 'img'){
                $src = $imgurl.'&w='.$x['w'].'&ipr='.urlencode(json_encode($x));
                $c = [
                    'client' =>[
                        'agent' => 'mobile',
                        'cookies'=>[],
                        'rawheaders'=> [
                            "Connection"=> 'keep-alive',
                            'Cache-Control'=> 'max-age=0',
                            'Upgrade-Insecure-Requests'=> 1,
                            'Accept-Encoding'=> 'gzip, deflate, sdch, br',
                            'Accept-Language'=> 'zh-CN,zh;q=0.8'
                        ]
                    ],
                    'url'=> $src
                ];
                $c['filename'] =sprintf('data/wk/%d/%d/%s',date("Ym"),date("d"),md5($src));
                $spider =  new Spider();
                $spider->fetch($c);
                $html .= sprintf("<{$x['t']} class=\"lazy\" data-original='/%s'>",$c['filename']);
            }else if (is_string($x['c']))
            {
                $html .= sprintf("<{$x['t']}>%s</{$x['t']}>",$x['c']);
            }else{
                $html .= sprintf("<{$x['t']}>%s</{$x['t']}>",self::docXml($x['c'],$imgurl));
            }

        }
        return $html;
    }
}