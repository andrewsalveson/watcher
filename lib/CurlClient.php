<?php namespace Sal;
class CurlClient{
  static function get($config){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config->url);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config->timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $response = '';
    switch(isset($config->responseType)?$config->responseType:null){
      case 'json':
        $response = json_decode($output);
        break;
      default:
        $response = $output;
        break;
    }
    return (object)[
      'RESPONSE'=>$response,
      'HTTP_CODE'=>$info['http_code']
    ];
  }
  static function get_json($config){
    $config->responseType = 'json';
    return CurlClient::get($config);
  }
  static function post($config){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config->url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $config->fields);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config->timeout);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $response = '';
    switch(isset($config->responseType)?$config->responseType:null){
      case 'json':
        $response = json_decode($output);
        break;
      default:
        $response = $output;
    }
    return (object)[
      'RESPONSE'=>$response,
      'HTTP_CODE'=>$info['http_code']
    ];
  }
}