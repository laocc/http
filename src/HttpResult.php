<?php
declare(strict_types=1);

namespace esp\http;

use function esp\helper\text;

class HttpResult
{
    /**
     * curl的错误，请求结果的code不等于200
     * 若请求结果有可能不是200也算正常情况，请在请求时加 $option['allow']=[203,205]
     */
    public int $_error = 0;
    public string $_message = '';

    public string $_decode = '';
    public array $_header = [];

    public array $_option = [];
    public array $_info = [];
    public float $_time = 0;
    public int $_code = 0;
    public int $_retry = 0;
    public string $_url = '';

    public string $_html = '';
    public array $_data = [];
    private int $_thenRun = 0;

    private int $limitPrintSize = 1024;

    public function __construct(array $option)
    {
        if (isset($option['size'])) $this->limitPrintSize = $option['size'];
    }

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
            'decode' => $this->_decode,
            'retry' => $this->_retry,
            'code' => $this->_code,
            'option' => $this->_option,
            'info' => $this->_info,
            'header' => $this->_header,
            'html' => $this->_html,
        ];
        if ($key) return $val[$key];

        if (isset($this->_option[CURLOPT_FILE])) return $val;//下载模式，不处理html

        if (intval($this->_info['size_download'] ?? 0) > $this->limitPrintSize) {
            $val['html'] = "下载内容超过1Kb，请通过RESULT->html()方式查询结果";
        }

//        $type = $this->_info['content_type'] ?? '';
//        if (!preg_match('/(text|xml|json|javascript|html)/i', $type)) {
//            $val['html'] = "下载内容非文本格式：{$type}，请通过RESULT->html()方式查询结果";
//        }
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
    public function setError(string $msg = null, int $error = 100): HttpResult
    {
        $this->_error = $error;
        $this->_message = $msg ?: "ERROR({$error})";
        return $this;
    }

    /**
     * 重试次数
     *
     * @return $this
     */
    public function reTry(): HttpResult
    {
        $this->_retry++;
        return $this;
    }

    public function setCode(int $code): HttpResult
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * @param array $value
     * @return $this
     */
    public function params(array $value): HttpResult
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
     * @param bool $mayEmpty
     * @return $this
     */
    public function decode(string $html, bool $mayEmpty = false): HttpResult
    {
        $this->_html = trim($html);
        if (empty($this->_html)) {
            if (!$mayEmpty) {//是否有可能为空
                $this->_message = '请求结果为空';
                $this->_error = 500;
            }
            return $this;
        }

        if (in_array($this->_decode, ['html', 'txt', 'text'])) {
            if (empty($this->_message)) $this->_message = 'ok';
            return $this;
        }

        $fstCode = $this->_html[0] ?? '';

        switch ($this->_decode) {

            case 'jsobject':
                $str = preg_replace(["/([a-zA-Z_]+[a-zA-Z0-9_]*)\s*:/", "/:\s*'(.*?)'/"],
                    ['"\1":', ': "\1"'], $this->_html);

                $this->_data = json_decode($str, true);
                if (empty($this->_data)) {
                    $this->_message = '请求结果jsObject无法转换为数组';
                    $this->_error = 500;
                }

                break;

            case 'json':

                if ($fstCode === '{' or $fstCode === '[') {
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
                if ($fstCode === '<') {
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
                $this->_data = $this->_html;
                break;
            default:

                if ($fstCode === '{' or $fstCode === '[') {
                    $this->_data = json_decode($this->_html, true);
                    if (empty($this->_data)) {
                        $this->_message = '请求结果JSON无法转换为数组';
                        $this->_error = 500;
                    }
                } else if ($fstCode === '<' and strpos($this->_html, 'html>') === false) {
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

    /**
     * 第一次执行，可以同时2个回调，分别为success和fail
     * 第二次执行，只执行fail，如果前面有执行过fail，则本次忽略
     * 第三次以后执行回调的第一个参数为html
     * @param callable $success
     * @param callable|null $fail
     * @param callable|null $complete
     * @return $this
     *
     * success(data,info)
     * fail(error_code,info)
     * complete(html,info)
     */
    public function then(callable $success, callable $fail = null, callable $complete = null): HttpResult
    {
        $info = $this->info();

        if ($this->_thenRun > 1) {
            //已执行过2次以上，此时为complete回调
            $success($this->_html, $info);
            $this->_thenRun++;
            return $this;
        }

        if ($this->_thenRun === 1) {
            if ($this->_error) {
                //已执行过一次，此时为fail回调
                $success($this->_data, $info);
            }
            $this->_thenRun++;
            return $this;
        }

        //出错
        if ($this->_error) {
            if (is_callable($fail)) {
                $fail($this->_error, $info);
            }
        } else {
            //正常结果
            $success($this->_data, $info);
        }

        if (is_callable($complete)) $complete($this->_html, $info);

        $this->_thenRun++;
        return $this;
    }

}