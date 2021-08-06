<?php

/**
 * Http.php
 *
 *
 * @category   Http
 * @author     Rubén Pérez López
 * @date       04/08/2021
 * @copyright  2021 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    04/08/2021 v1.0
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Http
{
  private $defaultPrefix = '';

  public function __construct($defaultPrefix = '')
  {
    $this->defaultPrefix = $defaultPrefix;
  }

  public function get($url, $query)
  {
    return $this->call('GET', $this->getFullUri($url), $query);
  }

  public function post($url, $body)
  {
    return $this->call('POST', $this->getFullUri($url), $body);
  }

  public function put($url, $body)
  {
    return $this->call('PUT', $this->getFullUri($url), $body);
  }

  public function delete($url, $body)
  {
    return $this->call('DELETE', $this->getFullUri($url), $body);
  }

  private function getFullUri($url)
  {
    if (strpos($url, 'http') === 0) {
      return $url;
    } else if (strpos($url, '/') === 0) {
      return $this->defaultPrefix . $url;
    } else {
      return $this->defaultPrefix . '/' . $url;
    }
  }

  private static function call($method, $url, $data)
  {
    $curl = curl_init();
    switch ($method) {
      case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;
      case "PUT":
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        if ($data) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;
      default:
        if ($data) {
          $url = sprintf("%s?%s", $url, http_build_query($data));
        }
    }
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    $array_headers = array(
      'Content-Type: application/json'
    );
    /*if ($headers) {
            array_push($array_headers, $headers);
        }*/
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // EXECUTE:
    $result = curl_exec($curl);
    if (!$result) {
      die("Connection Failure");
    }
    curl_close($curl);
    return $result;
  }
}
