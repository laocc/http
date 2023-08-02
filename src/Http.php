<?php
declare(strict_types=1);

namespace esp\http;

use esp\error\Error;
use esp\helper\library\ext\Xml;
use function esp\helper\mk_dir;
use function esp\helper\root;
use function esp\helper\is_ip;

final class Http
{
    private array $option = [];
    private array $files = [];
    private string $url = '';
    private \CURLFile $file;
    private $data;//要post的数据，可能是数组，或字串

    public function __construct($param = null, array $option = [])
    {
        if (is_array($param)) {
            $this->option = $param;
        } else if (is_string($param)) {
            $this->url = trim($param, '/');
            $this->option = $option;
        }
    }


    public function flush(): Http
    {
        $this->option = [];
        $this->url = '';
        $this->data = null;
        return $this;
    }

    public function debug(): array
    {
        return [
            'url' => $this->url,
            'option' => $this->option,
            'data' => $this->data,
        ];
    }


    /**
     * 请求目标
     * @param string $url
     * @return $this
     */
    public function url(string $url): Http
    {
        $this->url = trim($url, '/');
        return $this;
    }

    /**
     * 发送的数据
     *
     * 若传入的是数组，在发送时会被http_build_query($this->data)编码
     *
     * 若需要发送json或xml等格式，请事先编码好再传入
     * @param $data
     * @return $this
     */
    public function data($data): Http
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 常规加签名
     *
     * 验签 $this->post->signCheck(['token' => 'ddd'])
     * @param string $token
     * @param string $tKey
     * @param string $sKey
     * @param string|null $signString
     * @return $this|string
     */
    public function sign(string $token, string $tKey = 'key', string $sKey = 'sign', string &$signString = null)
    {
        if (!is_array($this->data)) return '调用http->sign()前传入的data须为数组格式';
        $data = $this->data;
        ksort($data);
        $str = [];
        foreach ($data as $k => $v) {
            if ($v === '' or is_null($v)) continue;
            if (is_bool($v)) $v = intval($v);
            else if (is_array($v)) $v = json_encode($v, 320);
            if (!is_string($v)) $v = strval($v);
            $str[] = "{$k}={$v}";
        }
        $str = implode('&', $str);
        $signString = "{$str}&{$tKey}={$token}";
        $this->data[$sKey] = md5($signString);
        return $this;
    }

    /**
     * 发送数据的编码方法
     * @param string $encode
     * @return $this
     */
    public function encode(string $encode = ''): Http
    {
        if (!in_array($encode, ['json', 'xml', 'url'])) $encode = '';
        $this->option['encode'] = $encode;
        return $this;
    }

    /**
     * 设置解析目标数据的方法
     * @param string $encode
     * @return $this
     */
    public function decode(string $encode = ''): Http
    {
        if (!in_array($encode, ['json', 'jsobject', 'xml', 'html', 'text', 'txt', 'auto'])) $encode = '';
        $this->option['decode'] = $encode;
        return $this;
    }

    /**
     * 上传文件，可多次传入，但不可以传入相同文件
     *
     * @param string $filename
     * @param string $mime_type
     * @param string $posted_filename
     * @return $this
     * @throws Error
     */
    public function file(string $filename, string $mime_type = '', string $posted_filename = ''): Http
    {
        if (!is_readable($filename)) throw new Error("文件({$filename})不可读");
        $this->files['_up_' . md5($filename)] = new \CURLFile($filename, $mime_type, $posted_filename);
        return $this;
    }

    /**
     * 结果保存到文件，即下载文件
     *
     * @param string $filename
     * @return $this
     * @throws Error
     */
    public function save(string $filename): Http
    {
        $this->option['save'] = $filename;
        mk_dir($filename);
        return $this;
    }


    /**
     * 指定主机，在已知目标服务器IP时用，会加快速度，
     * 当然，也可以任意指定IP，
     * 比如请求api.qq.com时，将IP指向 123.123.23.45 ，则会只向此IP请求
     * 只要这个服务器有绑定api.qq.com域名的主机，则可正常请求
     *
     * @param string $host
     * @return $this
     */
    public function host(string $host): Http
    {
        if (is_ip($host)) {
            $this->option['host'] = $host;
        } else {
            //若不指定，则在后面会从URL中提取
            $this->option['host_domain'] = $host;
        }
        return $this;
    }

    /**
     * 指定主机端口，若不设置，则自动判断80或443
     * @param string $port
     * @return $this
     */
    public function port(string $port): Http
    {
        $this->option['port'] = $port;
        return $this;
    }

    /**
     * 语言，中文cn，英文en
     * @param string $lang
     * @return $this
     */
    public function lang(string $lang = 'cn'): Http
    {
        $this->option['lang'] = $lang;
        return $this;
    }

    /**
     * 指定referer
     * @param string $referer
     * @return $this
     */
    public function referer(string $referer): Http
    {
        $this->option['referer'] = $referer;
        return $this;
    }

    /**
     * 指定客户端ip
     * @param string $ip
     * @return $this
     */
    public function ip(string $ip): Http
    {
        $this->option['ip'] = $ip;
        return $this;
    }

    /**
     * 请求密码
     * @param string $pwd
     * @return $this
     */
    public function password(string $pwd): Http
    {
        $this->option['auth'] = $pwd;
        return $this;
    }

    /**
     * 是否跟随 redirect 跳转次数，最小值2
     * @param int $num
     * @return $this
     */
    public function redirect(int $num = 2): Http
    {
        $this->option['redirect'] = $num;
        return $this;
    }

    /**
     * 请求等待（连接阶段）
     * @param int $scd
     * @return $this
     */
    public function wait(int $scd = 10): Http
    {
        $this->option['wait'] = $scd;
        return $this;
    }

    /**
     * @param bool $scd
     * @return $this
     */
    public function transfer(bool $scd = true): Http
    {
        $this->option['transfer'] = $scd;
        return $this;
    }

    /**
     * 请求等待时间（运行阶段）
     * @param int $scd
     * @return $this
     */
    public function timeout(int $scd = 10): Http
    {
        $this->option['timeout'] = $scd;
        return $this;
    }

    /**
     * 指定header，可多次指定
     *
     * @param string $header
     * @param string|null $value
     * @return $this
     */
    public function headers(string $header, string $value = null): Http
    {
        if (is_null($value)) {
            $this->option['headers'][] = $header;
        } else {
            $this->option['headers'][$header] = $value;
        }
        return $this;
    }

    /**
     * 指定代理服务器
     *
     * @param string $proxy
     * @return $this
     */
    public function proxy(string $proxy): Http
    {
        $this->option['proxy'] = $proxy;
        return $this;
    }

    /**
     * 如果请求异常，再次重试
     *
     * @param int $times 重试次数，0为不重试，最大3次
     * @param int $wait 重试等待时间，ms，最小10ms，最大1000ms
     * @return $this
     */
    public function retry(int $times = 1, int $wait = 100): Http
    {
        $this->option['retry'] = $times;
        $this->option['retry_wait'] = $wait;
        return $this;
    }

    /**
     * 编码转换
     * @param string $charset
     * @return $this
     */
    public function charset(string $charset = 'utf8'): Http
    {
        $this->option['charset'] = $charset;
        return $this;
    }

    /**
     * 设置，或读取 cookies
     * 可以直接指定一个Cookies文本，或指定为一个文件
     * 当指定为文件时，若请求目标有写出Cookies，则会写入指定的文件中
     * 要保证目标文件的目录有读写权限，这里不做权限检查
     *
     * @param string|null $cookies
     * @return $this|bool|mixed|string
     */
    public function cookies(string $cookies = null)
    {
        if (is_null($cookies)) {
            if (substr($this->option['cookies'] ?? '', 0, 1) === '/') {
                return file_get_contents($this->option['cookies']);
            } else {
                return $this->option['cookies'];
            }
        }
        if ($cookies === 'temp' or $cookies === 'rand') {
            $cookies = "/tmp/ck_" . microtime(true) . mt_rand();
        }
        $this->option['cookies'] = $cookies;
        return $this;
    }

    /**
     * 浏览器
     * @param string $ua
     * @return $this
     */
    public function ua(string $ua): Http
    {
        $this->option['agent'] = $ua;
        return $this;
    }

    /**
     * 请求方法
     * @param string $method
     * @return $this
     */
    public function method(string $method): Http
    {
        $this->option['type'] = $method;
        return $this;
    }

    /**
     * 模仿真人
     * @return $this
     */
    public function human(): Http
    {
        $this->option['human'] = true;
        return $this;
    }

    /**
     * gZip解压
     * @return $this
     */
    public function gzip(): Http
    {
        $this->option['gzip'] = true;
        return $this;
    }

    /**
     * 带回信息流
     * @param bool $header
     * @return $this
     */
    public function header(bool $header = true): Http
    {
        $this->option['header'] = $header;
        return $this;
    }

    /**
     * 检查证书等级
     * 1 是检查服务器SSL证书中是否存在一个公用名(common name)。
     *      译者注：公用名(Common Name)一般来讲就是填写你将要申请SSL证书的域名 (domain)或子域名(sub domain)。
     * 2，会检查公用名是否存在，并且是否与提供的主机名匹配。
     * 0 为不检查名称。
     * 在生产环境中，这个值应该是 2（默认值）。
     * @param int $level
     * @return $this
     */
    public function ssl(int $level = 2): Http
    {
        if ($level > 2) $level = 2;
        if ($level < 0) $level = 0;
        $this->option['ssl'] = $level;
        return $this;
    }


    /**
     * 携带证书
     * @param $key
     * @param null $value
     * @return $this
     */
    public function cert($key, $value = null): Http
    {
        if (is_array($key)) {
            $this->option['cert'] = $key;
        } else {
            //cert,key,ca
            $this->option['cert'][$key] = $value;
        }
        return $this;
    }


    /**
     * 直接设置option的子项
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function set($key, $value = null): Http
    {
        if (is_array($key)) {
            $this->option = array_merge($this->option, $key);
        } else {
            $this->option[$key] = $value;
        }
        return $this;
    }

    /**
     * @param string|null $url
     * @return string
     */
    private function reUrl(string $url = null): string
    {
        if (!$url) return $this->url;
        if ($url[0] === '/') {
            $this->url = $this->url . $url;
        } else if (substr($url, 0, 4) === 'http') {
            $this->url = $url;
        } else {
            $this->url = "{$this->url}/{$url}";
        }
        return $this->url;
    }

    /**
     * get方式读取
     * @param string $url
     * @return HttpResult
     */
    public function get(string $url = ''): HttpResult
    {
        $this->reUrl($url);
        $this->option['type'] = 'get';
        return $this->request();
    }

    /**
     * post方式
     * @param string $url
     * @return HttpResult
     */
    public function post(string $url = ''): HttpResult
    {
        $this->reUrl($url);
        $this->option['type'] = 'post';
        return $this->request();
    }

    /**
     * 上传文件，所传内容须为数组，且，其中至少要有一个字段 = new \CURLFile($file);
     * @param string $url
     * @return HttpResult
     * @throws Error
     */
    public function upload(string $url = ''): HttpResult
    {
        $this->reUrl($url);
        $this->option['type'] = 'upload';
        return $this->request();
    }

    /**
     * @param string|null $url
     * @return HttpResult
     *
     * $option['type']      请求方式，get,post,upload
     * $option['port']      对方端口
     * $option['gzip']      被读取的页面有gzip压缩
     * $option['headers']   携带的头信息
     * $option['header']    返回文本流全部信息，在返回的header里
     * $option['agent']     模拟的客户端UA信息
     * $option['proxy']     代理服务器IP
     * $option['cookies']   带出的Cookies信息，或cookies文件
     * $option['referer']   指定来路URL
     * $option['cert']      带证书
     * $option['charset']   目标数据转换格式，默认utf-8
     * $option['redirect']  是否跟着跳转，>0时为跟着跳
     * $option['encode']    发送post若数据是数组，将进行转换，可选：json,xml,query
     * $option['decode']    将目标html转换为数组，在返回的array里，可选：json,xml
     * $option['host']      目标域名解析成此IP
     * $option['ip']        客户端IP，相当于此cURL变成一个代理服务器
     * $option['lang']      语言，cn或en
     * $option['ssl']       SSL检查等级，0，1或2，默认2
     * $option['host_domain']       有些情况下curl总是会缓存DNS，加此选项把域名改为IP可避免此情况，目前发现在php8.2中会这样
     * @throws Error
     */
    public function request(string $url = null): HttpResult
    {
        $result = new HttpResult($this->option['result'] ?? []);
        $option = $this->option;
        $url = $this->reUrl($url);
        if (empty($url)) {
            return $result->setError('目标API为空');
        }
        if (strtolower(substr($url, 0, 4)) !== 'http') {
            return $result->setError('目标API须以Http开头');
        }

        if (!isset($option['headers'])) $option['headers'] = array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];

        $cOption = [];

        /**
         * 试验性，生产环境勿用
         */
        if (isset($option['stderr'])) {
            $cOption[CURLOPT_VERBOSE] = true;//输出所有的信息，写入到STDERR(直接打印到屏幕)
            $cOption[CURLOPT_CERTINFO] = true;//TRUE 将在安全传输时输出 SSL 证书信息到 STDERR。
            $cOption[CURLOPT_FAILONERROR] = true;//当 HTTP 状态码大于等于 400，TRUE 将将显示错误详情。 默认情况下将返回页面，忽略 HTTP 代码。
            if (is_string($option['stderr'])) {
                $cOption[CURLOPT_STDERR] = fopen(root($option['stderr']), 'w');//错误信息输出到文件，若不指定，则输出到屏幕
            }
        }

        if (isset($option['host'])) {
            if (is_array($option['host'])) {
                /**
                 * host必须是array("example.com:80:127.0.0.1")格式
                 * 不再检查格式有效性
                 */
                $cOption[CURLOPT_RESOLVE] = $option['host'];
            } else {
                if (!is_ip($option['host'])) return $result->setError('Host必须是IP格式');
                $urlDom = explode('/', $url);
                $hasPort = strpos($urlDom[2], ':') > 0;
                //从url中提取端口
                if ($hasPort) {
                    $dom = explode(':', $urlDom[2]);
                    $urlDom[2] = $dom[0];
                    if (!isset($option['host_domain'])) $option['host_domain'] = $urlDom[2];
                    if (!isset($option['port'])) {
                        $option['port'] = intval($dom[1]);
                    } else if ($option['port'] !== intval($dom[1])) {
                        return $result->setError('指定的port与URL中的port不一致');
                    }
                } else {
                    if (!isset($option['host_domain'])) $option['host_domain'] = $urlDom[2];
                }

                if (!isset($option['port'])) {
                    if (strtolower(substr($url, 0, 5)) === 'https') {
                        $option['port'] = 443;
                    } else {
                        $option['port'] = 80;
                    }
                }
                $cOption[CURLOPT_RESOLVE] = ["{$urlDom[2]}:{$option['port']}:{$option['host']}"];

                /**
                 * 如果在 CURLOPT_RESOLVE 中指定了目标服务器的 IP 地址和 8000 端口号，
                 * 而在 URL 中不带端口号，
                 * 则 CURL 将使用 CURLOPT_RESOLVE 中指定的 IP 地址和 8000 端口号来建立连接和发送请求。
                 * 这种方式可以规避目标防火墙对非标准端口的限制，
                 * 因为 CURL 直接使用 IP 地址和端口号建立连接，不需要经过 DNS 解析和目标服务器的防火墙。
                 */
                $automaticPortPort = !$hasPort;//自动添加端口号到URL
                if (isset($option['automaticPort'])) $automaticPortPort = boolval($option['automaticPort']);
                if ($option['port'] === 443) $automaticPortPort = false;
                if ($option['port'] === 80) $automaticPortPort = false;
                if ($automaticPortPort) {
                    if ($option['domain2ip'] ?? 0) $urlDom[2] = $option['host'];
                    $urlDom[2] = "{$urlDom[2]}:{$option['port']}";
                    $url = implode('/', $urlDom);
                } else if ($option['domain2ip'] ?? 0) {
                    $urlDom[2] = $option['host'];
                    $url = implode('/', $urlDom);
                }
            }
        }

        if (isset($option['human'])) {
            $option['headers'][] = "Cache-Control: max-age=0";
            $option['headers'][] = "Connection: keep-alive";
            $option['headers'][] = "Upgrade-Insecure-Requests: 1";
            $option['headers'][] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.5,image/webp,image/apng,*/*;q=0.8";
        }

        if (isset($option['lang'])) {
            if ($option['lang'] === 'en') {
                $option['headers'][] = "Accept-Language: en-us,en;q=0.8";
            } elseif ($option['lang'] === 'cn') {
                $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8";
            }
        }

        /**
         * 允许重定向次数，最少2，最大10
         */
        if (isset($option['redirect'])) {
            $cOption[CURLOPT_POSTREDIR] = 7;//位掩码 到重定向网址:1 (301 永久重定向), 2 (302 Found) 和 4 (303 See Other)
            $cOption[CURLOPT_FOLLOWLOCATION] = true;//根据服务器返回 HTTP 头中的 "Location: " 重定向
            $cOption[CURLOPT_MAXREDIRS] = $option['redirect'];//指定最多的 HTTP 重定向次数，最小要为2
            $cOption[CURLOPT_AUTOREFERER] = true;//根据 Location: 重定向时，自动设置 header 中的Referer:信息
            $cOption[CURLOPT_UNRESTRICTED_AUTH] = true;//重定向时，继续发送用户名和密码信息，哪怕主机名已改变
            if ($cOption[CURLOPT_MAXREDIRS] < 2) $cOption[CURLOPT_MAXREDIRS] = 2;
            elseif ($cOption[CURLOPT_MAXREDIRS] > 10) $cOption[CURLOPT_MAXREDIRS] = 10;
        }

        if (isset($option['host_domain'])) $option['headers'][] = "HOST: {$option['host_domain']}";

        if (isset($option['ip'])) {     //指定客户端IP
            $option['headers'][] = "CLIENT-IP: {$option['ip']}";
            $option['headers'][] = "X-FORWARDED-FOR: {$option['ip']}";
        }

        if (isset($option['cookies']) and !empty($option['cookies'])) {//带Cookies
            if ($option['cookies'][0] === '/') {
                $cOption[CURLOPT_COOKIEFILE] = $option['cookies'];
                $cOption[CURLOPT_COOKIEJAR] = $option['cookies'];
            } else {
                $cOption[CURLOPT_COOKIE] = $option['cookies'];//直接指定值
            }
        }

        $option['type'] = strtoupper($option['type'] ?? 'get');
        if (!in_array($option['type'], ['GET', 'POST', 'PUT', 'HEAD', 'DELETE', 'UPLOAD'])) $option['type'] = 'GET';
        $cOption[CURLOPT_CUSTOMREQUEST] = $option['type'];

        switch ($option['type']) {
            case "GET" :
                $cOption[CURLOPT_HTTPGET] = true;
                if (!empty($this->data)) {//GET时，需格式化数据为字符串
                    if (is_array($this->data)) {
                        $url .= (!strpos($url, '?') ? '?' : '&') . http_build_query($this->data);
                        $this->data = null;
                    }
                }
                break;

            case "POST":
                if (is_array($this->data)) {
                    $encode = ($option['encode'] ?? '');
                    if ($encode === 'json') {
                        $this->data = json_encode($this->data, 256 | 64);
                        if (!isset($option['headers']['Content-Type'])) {
                            $option['headers']['Content-Type'] = "application/json;charset=UTF-8";
                        }
                    } else if ($encode === 'xml') {
                        $this->data = $this->xml($this->data);
                        if (!isset($option['headers']['Content-Type'])) {
                            $option['headers']['Content-Type'] = "application/xml;charset=UTF-8";
                        }
                    } else {
                        $this->data = http_build_query($this->data);
                        if (!isset($option['headers']['Content-Type'])) {
                            $option['headers']['Content-Type'] = "application/x-www-form-urlencoded;charset=UTF-8";
                        }
                    }
                }

//                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $option['headers'][] = "Expect: ";  //post大于1024时，会带100 ContinueHTTP标头的请求，加此指令禁止
                $cOption[CURLOPT_POST] = true;      //类型为：application/x-www-form-urlencoded
                $cOption[CURLOPT_POSTFIELDS] = $this->data;
                break;

            case "UPLOAD":
                if (!is_array($this->data)) throw new Error('传送文件时，data须为数组格式');
                $hasCurlFile = false;
                if (!empty($this->files)) {
                    $this->data += $this->files;
                    $hasCurlFile = true;
                }
                if (!$hasCurlFile) {
                    foreach ($this->data as $f => $dt) {
                        if ($dt instanceof \CURLFile) {
                            $hasCurlFile = true;
                            break;
                        }
                    }
                }
                if (!$hasCurlFile) {
                    throw new Error('传送文件时，data中至少要有一个= new \CURLFile($file)类型的文件，或用$http->file(...)传入文件');
                }
                if (!isset($option['headers']['Content-Type'])) {
                    $option['headers']['Content-Type'] = "multipart/form-data;charset=UTF-8";
                }
                $option['headers'][] = "Expect: ";  //post大于1024时，会带100 ContinueHTTP标头的请求，加此指令禁止
                $cOption[CURLOPT_CUSTOMREQUEST] = 'POST';
                $cOption[CURLOPT_POSTFIELDS] = $this->data;

                break;

            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                //不确定服务器支持这个自定义方法则不要使用它。
                break;
        }

        if (isset($option['auth'])) $cOption[CURLOPT_USERPWD] = $option['auth'];

        //指定代理
        if (isset($option['proxy'])) {
            if (strpos($option['proxy'], ';')) {
                $pro = explode(';', $option['proxy']);
                $cOption[CURLOPT_PROXY] = $pro[0];
                if (!empty($pro[1])) $cOption[CURLOPT_PROXYUSERPWD] = $pro[1];
            } else {
                $cOption[CURLOPT_PROXY] = $option['proxy'];
            }
        }

        if (isset($option['referer']) and $option['referer']) { //来源页
            $cOption[CURLOPT_REFERER] = $option['referer'];
        }

        if (isset($option['gzip']) and $option['gzip']) {   //有压缩
            $option['headers']['Accept-Encoding'] = "gzip, deflate";
            $cOption[CURLOPT_ENCODING] = "gzip, deflate";
        }

        if (!empty($option['headers'])) {
            $cOption[CURLOPT_HTTPHEADER] = $this->realHeaders($option['headers']);
        }

        if (isset($option['ua'])) $option['agent'] = $option['ua'];
        if (!isset($option['agent'])) $option['agent'] = 'HttpClient/cURL laoCC/esp laoCC/http';
        if (!empty($option['agent'])) $cOption[CURLOPT_USERAGENT] = $option['agent'];

        $cOption[CURLOPT_URL] = $url;            //接收页
        $cOption[CURLOPT_HEADER] = (isset($option['header']) and $option['header']);        //带回头信息
        $cOption[CURLOPT_DNS_CACHE_TIMEOUT] = ($option['dns'] ?? 120); //内存中保存DNS信息，默认120秒
        $cOption[CURLOPT_CONNECTTIMEOUT] = ($option['wait'] ?? 20);     //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = ($option['timeout'] ?? 10);       //允许执行的最长秒数，若用毫秒级，用TIMEOUT_MS
        $cOption[CURLOPT_IPRESOLVE] = ($option['ip_type'] ?? CURL_IPRESOLVE_V4); //指定使用IPv4解析
        $cOption[CURLOPT_RETURNTRANSFER] = ($option['transfer'] ?? true);//返回文本流，若不指定则是直接打印

        if ($cOption[CURLOPT_DNS_CACHE_TIMEOUT] === 0) {
            $cOption[CURLOPT_FRESH_CONNECT] = true;                         //强制新连接，不用缓存中的，禁用连接池
            $cOption[CURLOPT_FORBID_REUSE] = true;                         //强制新连接，即使连接池中有可用的连接，也会强制关闭并创建新的连接
        }

        if (isset($option['save'])) {
            $cOption[CURLOPT_FILE] = fopen(root($option['save']), 'w');
            $cOption[CURLOPT_RETURNTRANSFER] = true;
        }

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {
            if (!isset($option['ssl'])) $option['ssl'] = 2;
            /**
             * 1 是检查服务器SSL证书中是否存在一个公用名(common name)。
             *      译者注：公用名(Common Name)一般来讲就是填写你将要申请SSL证书的域名 (domain)或子域名(sub domain)。
             * 2，会检查公用名是否存在，并且是否与提供的主机名匹配。
             * 0 为不检查名称。
             * 在生产环境中，这个值应该是 2（默认值）。
             */
            if ($option['ssl'] > 0) {
                if ($option['ssl'] > 2) $option['ssl'] = 2;
                $cOption[CURLOPT_SSL_VERIFYPEER] = true;
                $cOption[CURLOPT_SSL_VERIFYHOST] = intval($option['ssl']);
            } else {
                $cOption[CURLOPT_SSL_VERIFYPEER] = false;//禁止 cURL 验证对等证书，就是不验证对方证书
                $cOption[CURLOPT_SSL_VERIFYHOST] = 0;
            }

            if (isset($option['cert'])) {       //证书
                $cOption[CURLOPT_SSLCERTTYPE] = 'PEM';
                $cOption[CURLOPT_SSLKEYTYPE] = 'PEM';
                if (isset($option['cert']['cert'])) $cOption[CURLOPT_SSLCERT] = $option['cert']['cert'];
                if (isset($option['cert']['key'])) $cOption[CURLOPT_SSLKEY] = $option['cert']['key'];
                if (isset($option['cert']['ca'])) $cOption[CURLOPT_CAINFO] = $option['cert']['ca'];
            }
        }

        $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_NONE;//自动选择http版本

        //若有指定参数，优选用指定的参数
        if (isset($option['params']) and is_array($option['params'])) $cOption = $option['params'] + $cOption;

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) {
            return $result->setError('cURL初始化错误');
        }

        curl_setopt_array($cURL, $cOption);
        reTry:
        $time = microtime(true);
        if (isset($cOption[CURLOPT_FILE])) {
            $html = 'SAVE#' . $option['save'];
            $option['decode'] = 'text';
            curl_exec($cURL);
        } else {
            $html = curl_exec($cURL);
        }
        $info = curl_getinfo($cURL);
        $result->params([
            'start' => $time,
            'url' => $url,
            'info' => $info,
            'decode' => $option['decode'] ?? ($option['encode'] ?? ''),
            'time' => microtime(true) - $time,
            'post' => $this->data,
            'option' => $cOption,
        ]);
        if (($err = curl_errno($cURL)) > 0) {

            if (isset($option['retry']) and $option['retry'] > 0) {
                $option['retry'] -= 1;
                $result->reTry();
                if ($option['retry_wait'] > 0) {
                    usleep($option['retry_wait'] * 1000);
                }
                goto reTry;
            }

            $result->setError(curl_error($cURL), $err);
            curl_close($cURL);
            return $result;
        }
        curl_close($cURL);

        if (isset($option['header']) and $option['header']) {
            $result->header(substr($html, 0, $info['header_size']));
            $html = trim(substr($html, $info['header_size']));
        }

        if ($info['content_type'] && preg_match('/charset=([gbk231]{3,6})/i', $info['content_type'], $chat)) {
            $html = mb_convert_encoding($html, 'UTF-8', $chat[1]);

        } else if (isset($option['charset'])) {
            if ($option['charset'] === 'auto') {
                //自动识别gbk/gb2312转换为utf-8
                if (preg_match('/<meta.+?charset=[\'\"]?([gbk231]{3,6})[\'\"]?/i', $html, $chat)) {
                    $option['charset'] = $chat[1];
                } else {
                    $option['charset'] = null;
                }
            }
            if (is_null($option['charset'])) {
                $html = mb_convert_encoding($html, 'UTF-8');
            } else {
                $html = mb_convert_encoding($html, 'UTF-8', $option['charset']);
            }
        }

        $result->setCode($code = intval($info['http_code']));
        if (!in_array($code, array_merge($option['allow'] ?? [], [200, 204]))) {
            if ($code === 0) {
                $code = 10;
                $result->setCode($code);
            }
            $result->setError($html, $code);
        }

        return $result->decode($html, $option['may_empty'] ?? false);
    }

    private function realHeaders(array $heads): array
    {
        $array = [];
        foreach ($heads as $h => $head) {
            if (is_string($h)) {
                $array[$this->Camelize($h)] = trim(strval($head));
            } else {
                $str = explode(':', $head, 2);
                if (isset($str[1])) $array[$this->Camelize($str[0])] = trim($str[1]);
            }
        }
        $heads = [];
        foreach ($array as $k => $str) $heads[] = "{$k}: " . trim($str, ';');
        return $heads;
    }

    private function Camelize(string $str): string
    {
        return implode('-', array_map(function ($s) {
            return ucfirst($s);
        }, explode('-', str_replace('_', '-', strtolower($str)))));
    }

    /**
     * @throws Error
     */
    private function xml(array $xml, $notes = null): string
    {
        return (new Xml($xml, $notes ?: 'xml'))->render(false);
    }

}
