<?php
declare(strict_types=1);

namespace esp\http;

class Rpc
{
    private $conf;
    private $_encode = 'json';
    private $_decode = 'json';

    public function __construct(array $conf = null)
    {
        $this->conf = $conf;
        if (is_null($conf)) {
            if (defined('_RPC')) {
                $this->conf = _RPC;
            } else {
                throw new \Error('未指定rpc也未定义_RPC');
            }
        }
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

    public function get(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        return $rpcObj->rpc($this->conf)
            ->encode($this->_encode)
            ->decode($this->_decode)
            ->get($uri)
            ->data($key);
    }

    public function post(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        return $rpcObj->rpc($this->conf)
            ->encode($this->_encode)
            ->decode($this->_decode)
            ->post($uri)
            ->data($key);
    }

}