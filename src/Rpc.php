<?php
declare(strict_types=1);

namespace esp\http;

use function esp\core\esp_error;

class Rpc
{
    private array $conf;
    private string $_encode = 'json';
    private string $_decode = 'json';
    private array $_allow = [];

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

        $this->conf = ['host' => $conf['host'], 'port' => $conf['port'], 'ip' => $conf['ip']];
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
        $option = [];
        $option['host_domain'] = $this->conf['host'];
        $option['host'] = $this->conf['ip'];
        $option['port'] = $this->conf['port'];
        $option['timeout'] = 5;
        $option['dns'] = 0;
        $option['domain2ip'] = 1;
        $option['encode'] = $this->_encode;
        $option['decode'] = $this->_decode;
        $option['ua'] = 'esp/http http/cURL http/rpc rpc/1.0.1';

        $url = sprintf('%s://%s:%s/%s', 'http', $this->conf['host'], $this->conf['port'], ltrim($uri, '/'));
        $http = new Http($option);
        if ($data) $http->data($data);

        if ($isPost) {
            $request = $http->post($url);
        } else {
            $request = $http->get($url);
        }

        if ($err = $request->error(true, $this->_allow)) return $err;
        if ($this->_decode !== 'json') return $request->html();

        return $request->data();
    }

}