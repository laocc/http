<?php

namespace esp\http;


class Rpc
{
    private $conf;

    public function __construct(array $conf = null)
    {
        $this->conf = $conf;
        if (is_null($conf) and defined('_RPC')) {
            $this->conf = _RPC;
        }
    }

    public function get(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        $rpc = $rpcObj->rpc($this->conf)->encode('json')->get($uri)->data();
        if ($key) return $rpc[$key] ?? null;
        return $rpc;
    }

    public function post(string $uri, string $key = null)
    {
        $rpcObj = new Http();
        $rpc = $rpcObj->rpc($this->conf)->encode('json')->post($uri)->data();
        if ($key) return $rpc[$key] ?? null;
        return $rpc;
    }

}