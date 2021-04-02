<?php
declare(strict_types=1);

namespace esp\http;

use function esp\http\helper\text;

class Result
{
    /**
     * curl的错误，请求结果的code不等于200
     * 若请求结果有可能不是200也算正常情况，请在请求时加 $option['allow']=[203,205]
     */
    public $_error = 0;
    public $_message = '';

    public $_encode = '';
    public $_header = [];

    public $_option = [];
    public $_info = [];
    public $_time = 0;
    public $_code = 0;
    public $_url;

    public $_html = '';
    public $_data = [];


    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return $this->info();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this->info(), 256 | 64);
    }

    /**
     * @param string|null $key
     * @return array|mixed
     */
    public function info(string $key = null)
    {
        $val = [
            'url' => $this->_url,
            'error' => $this->_error,
            'message' => $this->_message,
            'time' => $this->_time,
            'encode' => $this->_encode,
            'code' => $this->_code,
            'option' => $this->_option,
            'info' => $this->_info,
            'header' => $this->_header,
            'html' => $this->_html,
        ];
        if ($key) return $val[$key];

        return $val;
    }

    /**
     * 检查数据结果是否有异常
     * @param bool $chkErrSuccess 是否检查data中error/success
     * @param array $allowState data.error排除的情况
     * @return string|null
     *
     * 此方法只能应对请求结果中含有success和error的情况，其他结构时，请自行读取->data()再判断
     *
     */
    public function error(bool $chkErrSuccess = true, array $allowState = [])
    {
        if (!$this->_error) {
            if ($chkErrSuccess) {
                if ($this->_data['error'] ?? 0 && !in_array($this->_data['error'], $allowState)) {
                    return $this->_message;
                }
                if (!($this->_data['success'] ?? 1)) return $this->_message;
            }
            return null;
        }
        return $this->_message;
    }

    /**
     * @param string|null $msg
     * @param int $error
     * @return $this
     */
    public function setError(string $msg = null, int $error = 100): Result
    {
        $this->_error = $error;
        $this->_message = $msg ?: "ERROR({$error})";
        return $this;
    }

    public function setCode(int $code): Result
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * @param array $value
     * @return $this
     */
    public function params(array $value): Result
    {
        foreach ($value as $key => $val) {
            $this->{"_{$key}"} = $val;
        }
        return $this;
    }

    /**
     * @param string|null $text
     * @return $this|array
     */
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

    /**
     * @param string|null $key
     * @return mixed|null
     */
    public function data(string $key = null)
    {
        if ($key) return $this->_data[$key] ?? null;
        return $this->_data;
    }

    /**
     * @return string
     */
    public function html(): string
    {
        return $this->_html;
    }

    /**
     * @return string
     */
    public function text(): string
    {
        if (!$this->_html) return '';
        return text($this->_html);
    }

    /**
     * @param string $html
     * @return $this
     */
    public function decode(string $html): Result
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