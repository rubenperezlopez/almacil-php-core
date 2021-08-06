<?php

/**
 * translate.class.php
 *
 *
 * @category   Translate
 * @author     Rubén Pérez López
 * @date       30/04/2018
 * @copyright  2018 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    03/08/2021 v4.0
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Translate
{
  private $path = __DIR__ . '/../i18n';
  private $lang = 'en';
  private $findMissingTranslations = false;

  private $translations;

  public function __construct($lang = 'en', $path = __DIR__ . '/../i18n', $findMissingTranslations = false)
  {
    $this->path = implode('/', explode('//', $path . '/'));;
    $this->lang = $lang;
    $this->findMissingTranslations = $findMissingTranslations;

    if (!file_exists($path)) {
      mkdir($path);
    }

    $this->translations = $this->getFile($this->getFileName());
  }

  public function t($text, $params = null)
  {
    return $this->get($text, $params);
  }

  public function get($text, $params = null)
  {
    if ($this->translations->{$text} == '') {
      if ($this->findMissingTranslations) {
        $resp = $this->missingTranslation($text);
      } else {
        $resp = $text;
      }
    } else {
      $resp = $this->translations->{$text};
      if (isset($params)) {
        foreach ($params as $clave => $valor) {
          if (isset($valor) && isset($clave)) {
            $resp = implode($valor, explode('{{' . $clave . '}}', $resp));
          }
        }
      }
    }
    return str_replace("'", "&#39;", $resp);
  }

  public function getFileName()
  {
    return $this->path . $this->lang . '.json';
  }

  private function missingTranslation($text)
  {
    $this->translations = $this->getFile($this->getFileName());

    $gt = json_decode($this->callAPI('GET', 'http://traductor.almacil.com/api/?lng=' . urlencode($this->lang) . '&txt=' . urlencode($text), false));
    $this->translations->{$text} = $gt->traduccion;

    $handle = fopen($this->file, "w");
    fwrite($handle, json_encode($this->translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $this->translations->{$text};
  }


  private static function getFile($file)
  {
    if (!file_exists($file)) {
      $fileData = new \stdClass();
    } else {
      $fileContent = file_get_contents($file);
      if ($fileContent == '') {
        $fileData = new \stdClass();
      } else {
        $fileData = json_decode($fileContent);
      }
    }
    return $fileData;
  }
  private static function callAPI($method, $url, $data)
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
