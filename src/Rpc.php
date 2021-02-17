<?php
declare(strict_types=1);

namespace esp\http;


class Rpc
{
    private $conf;
    private $_encode = 'json';

    public function __construct(array $conf = null)
    {
        $this->conf = $conf;
        if (is_null($conf) and defined('_RPC')) {
            $this->conf = _RPC;
        }
    }

    public function encode(string $code): Rpc
    {
        $this->_encode = $code;
        return $this;
    }

    public function get(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        return $rpcObj->rpc($this->conf)->encode($this->_encode)->get($uri)->data($key);
    }

    public function post(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        return $rpcObj->rpc($this->conf)->encode($this->_encode)->post($uri)->data($key);
    }

}