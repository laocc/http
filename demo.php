<?php


$http = new \esp\http\Http();

$get = $http->url('http://www.demo.com/api/main')->get('json');

print_r($get);



//
