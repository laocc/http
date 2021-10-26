<?php


$http = new \esp\http\Http('http://www.demo.com/');

$http->get('/api/main')
    ->then(
        function ($data, $resp) {
            print_r($data);
            print_r($resp);
        })
    ->then(
        function ($data, $resp) {
            print_r($data);
            print_r($resp);
        }
    );




//
