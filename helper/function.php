<?php

namespace esp\http\helper;


function is_ip(string $value, string $which = 'ipv4'): bool
{
    if (empty($value) or !is_string($value)) return false;
    switch (strtolower($which)) {
        case 'ipv4':
            $which = FILTER_FLAG_IPV4;
            break;
        case 'ipv6':
            $which = FILTER_FLAG_IPV6;
            break;
        default:
            $which = NULL;
            break;
    }
    return (bool)filter_var($value, FILTER_VALIDATE_IP, $which);
}

function text(string $html, int $star = null, int $stop = null): string
{
    if ($stop === null) list($star, $stop) = [0, $star];
    $v = preg_replace(['/\&lt\;(.*?)\&gt\;/is', '/&[a-z]+?\;/', '/<(.*?)>/is', '/[\s\x20\xa\xd\'\"\`]/is'], '', trim($html));
    $v = str_ireplace(["\a", "\b", "\f", "\s", "\t", "\n", "\r", "\v", "\0", "\h", '  ', " ", "　", "	", ' '], '', $v);
    return htmlentities(mb_substr($v, $star, $stop, 'utf-8'));
}

