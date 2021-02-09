<?php

namespace esp\http;


use function esp\http\helper\text;

class Result
{
    private $_error = 0;
    private $_message = '';

    private $_encode = '';
    private $_header = [];

    private $_option = [];
    private $_info = [];
    private $_time = 0;
    private $_url;
    private $_post;

    private $_html;
    private $_data;


    public function __debugInfo()
    {
        return $this->info();
    }

    public function __toString(): string
    {
        return json_encode($this->info(), 256 | 64);
    }

    public function info(string $key = null)
    {
        $val = [
            'url' => $this->_url,
            'error' => $this->_error,
            'message' => $this->_message,
            'time' => $this->_time,
            'encode' => $this->_encode,
            'option' => $this->_option,
            'info' => $this->_info,
        ];
        if ($key) return $val[$key];

        return $val;
    }

    public function error()
    {
        if (!$this->_error) return null;
        return $this->_message;
    }

    public function setError(string $msg = null, int $error = 100)
    {
        $this->_error = $error;
        $this->_message = $msg;
        return $this;
    }

    public function params(array $value)
    {
        foreach ($value as $key => $val) {
            $this->{"_{$key}"} = $val;
        }
        return $this;
    }

    public function header(string $text = null)
    {
        if (is_null($text)) return $this->_header;
        $line = explode("\r\n", trim($text));
        foreach ($line as $i => $ln) {
            if (strpos($ln, ':')) {
                $tmp = explode(':', $ln, 2);
                $this->_header[strtoupper($tmp[0])] = trim($tmp[1]);
            } else {
                $this->_header[] = $ln;
            }
        }
        return $this;
    }

    public function data(string $key = null)
    {
        if ($key) return $this->_data[$key] ?? null;
        return $this->_data;
    }

    public function html()
    {
        return $this->_html;
    }

    public function text()
    {
        return text($this->_html);
    }

    public function decode(string $html)
    {
        $this->_html = $html;
        if (empty($html)) return $this;

        if (in_array($this->_encode, ['html', 'txt', 'text'])) {
            if (empty($this->_message)) $this->_message = 'ok';
            return $this;
        }

        switch ($this->_encode) {

            case 'json':

                if ($this->_html[0] === '{' or $this->_html[0] === '[') {
                    $this->_data = json_decode($this->_html, true);
                    if (empty($this->_data)) {
                        $this->_message = '请求结果JSON无法转换为数组';
                        $this->_error = 500;
                    }
                } else {
                    $this->_message = '请求结果不是json格式';
                    $this->_error = 500;
                }

                break;
            case 'xml':
                if ($this->_html[0] === '<') {
                    $this->_data = (array)simplexml_load_string(trim($this->_html), 'SimpleXMLElement', LIBXML_NOCDATA);
                    if (empty($this->_data)) {
                        $this->_message = '请求结果XML无法转换为数组';
                        $this->_error = 500;
                    }
                } else {
                    $this->_message = '请求结果不是XML格式';
                    $this->_error = 500;
                }

                break;
            case 'html':
            case 'text':
            case 'txt':
                //这几种情况，不尝试转换数组
                break;
            default:

                if ($this->_html[0] === '{' or $this->_html[0] === '[') {
                    $this->_data = json_decode($this->_html, true);
                    if (empty($this->_data)) {
                        $this->_message = '请求结果JSON无法转换为数组';
                        $this->_error = 500;
                    }
                } else if ($this->_html[0] === '<' and strpos($this->_html, 'html>') === false) {
                    try {
                        $this->_data = (array)simplexml_load_string(trim($this->_html), 'SimpleXMLElement', LIBXML_NOCDATA);
                    } catch (\Error $error) {

                    }
                    if (empty($this->_data)) {
                        $this->_message = '请求结果XML无法转换为数组';
                        $this->_error = 500;
                    }
                }

        }

        if (isset($this->_data['message'])) $this->_message = $this->_data['message'];
        else if (isset($this->_data['errMsg'])) $this->_message = $this->_data['errMsg'];
        else if (isset($this->_data['errmsg'])) $this->_message = $this->_data['errmsg'];
        else if (isset($this->_data['msg'])) $this->_message = $this->_data['msg'];
        else if (empty($this->_message)) $this->_message = 'ok';

        return $this;
    }


}