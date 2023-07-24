<?php
declare(strict_types=1);

namespace esp\http;

use esp\error\Error;

class Rpc
{
    private array $conf;
    private string $_encode = 'json';
    private string $_decode = 'json';
    private array $_allow = [];

    public function __construct(array $conf = null)
    {
        if (is_null($conf)) {
            if (defined('_RPC')) {
                $conf = _RPC;
            } else {
                throw new Error('未指定rpc也未定义_RPC');
            }
        }

        if (file_exists(_RUNTIME . '/master.lock')) $conf['ip'] = '127.0.0.1';
        $this->conf = $conf;
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

    public function get(string $uri, string $key = '')
    {
        return $this->request($uri, $key, false);
    }

    public function post(string $uri, string $key = '')
    {
        return $this->request($uri, $key, true);
    }

    public function request(string $uri, string $key, bool $isPost)
    {
        $option = [];
        $option['encode'] = $this->_encode;
        $option['decode'] = $this->_decode;
        $option['ua'] = 'esp/http rpc/' . getenv('SERVER_ADDR') . ':' . getenv('SERVER_PORT');

        $rpcObj = new Http($option);
        $http = $rpcObj->rpc($this->conf);
        if ($isPost) {
            $request = $http->post($uri);
        } else {
            $request = $http->get($uri);
        }

        if ($err = $request->error(true, $this->_allow)) return $err;

        return $request->data($key);
    }

}