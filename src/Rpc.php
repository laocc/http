<?php
declare(strict_types=1);

namespace esp\http;

use function esp\core\esp_error;

class Rpc
{
    private string $_encode = 'json';
    private string $_decode = 'json';
    private array $_allow = [];
    private string $url;
    public Http $http;
    public HttpResult $result;

    public function __construct(array $conf = [])
    {
        if (empty($conf)) {
            if (defined('_RPC')) {
                $conf = _RPC;
            } else {
                esp_error('未指定rpc也未定义_RPC');
            }
        }
        $masterIP = defined('_RPC') ? _RPC['ip'] : ($conf['master'] ?? '');

        if (file_exists(_RUNTIME . '/master.lock') and ($conf['ip'] === $masterIP)) {
            $conf['ip'] = '127.0.0.1';
        }

        $option = [];
        $option['host_domain'] = $conf['host'];
        $option['host'] = $conf['ip'];
        $option['port'] = $conf['port'];
        $option['timeout'] = 5;
        $option['dns'] = 0;
//        $option['domain2ip'] = 1;
        $option['encode'] = $this->_encode;
        $option['decode'] = $this->_decode;
        $option['ua'] = 'esp/http http/cURL http/rpc rpc/1.1.2';

        $this->http = new Http($option);
        $this->url = sprintf('%s://%s:%s', 'http', $conf['ip'], $conf['port']);
    }

    public function setUrl(string $url): Rpc
    {
        $this->url = $url;
        return $this;
    }

    public function encode(string $code): Rpc
    {
        $this->_encode = $code;
        return $this;
    }

    public function decode(string $code): Rpc
    {
        $this->_decode = $code;
        return $this;
    }

    public function allow(int $code): Rpc
    {
        $this->_allow[] = $code;
        return $this;
    }

    public function get(string $uri, array $data = [])
    {
        return $this->request($uri, $data, false);
    }

    public function post(string $uri, array $data = [])
    {
        return $this->request($uri, $data, true);
    }

    public function request(string $uri, array $data, bool $isPost)
    {
        if ($data) $this->http->data($data);

        if ($isPost) {
            $this->result = $this->http->post($this->url . $uri);
        } else {
            $this->result = $this->http->get($this->url . $uri);
        }

        if ($err = $this->result->error(true, $this->_allow)) return $err;
        if ($this->_decode !== 'json') return $this->result->html();

        return $this->result->data();
    }

}