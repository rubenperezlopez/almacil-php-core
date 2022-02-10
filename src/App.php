<?php

/**
 * App.php
 *
 *
 * @category   App
 * @author     Rubén Pérez López
 * @date       03/08/2021
 * @copyright  2021 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    24/08/2021 v1.0.5
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class App
{
  private $rootDir;
  private $config;
  private $language_short = 'en';
  private $language_long = 'en_GB';

  private $cacheFile;
  private $start;

  // Classes
  public $translate;
  public $database;
  public $router;
  public $http;

  private $environments;
  private $segments = [];
  private $query;
  private $body;

  private $data;

  private $hasError = false;
  private $objError;

  public function __construct($rootDir = __DIR__, $configFile = 'app.config.json')
  {

    $this->start = microtime(true);

    $this->rootDir = str_replace('//', '/', $rootDir . '/');

    $this->config = $this->getFile($this->rootDir . $configFile);

    $this->data = new \stdClass();

    // SECTION: ENVIRONMENT
    if (isset($this->config->environments)) {
      $baseEnv = $this->getFile($this->rootDir . $this->config->environments->baseFile);

      $keys = array_keys((array)$this->config->environments);
      for ($k = 0; $k < count($keys); $k++) {
        $key = $keys[$k];
        if ($key !== 'baseFile') {
          $item = $this->config->environments->{$key};
          if ($_SERVER['HTTP_HOST'] === $item->domain) {
            $propEnv = $this->getFile($this->rootDir . $item->propertiesFile);
          }
        }
      }
      $keys = array_keys((array)$propEnv);
      for ($k = 0; $k < count($keys); $k++) {
        $key = $keys[$k];
        $baseEnv->{$key} = $propEnv->{$key};
      }
      $this->environment = $baseEnv;
    }
    // !SECTION: ENVIRONMENT

    $display_errors = $this->config->display_errors ? 0 : 1;
    ini_set('display_errors', $display_errors);
    ini_set('display_startup_errors', $display_errors);
    error_reporting($display_errors);

    // SECTION: SEGMENTS
    $posint = strpos($_SERVER['REQUEST_URI'], '?');
    if ($posint > 0) {
      $this->segments = explode("/", substr($_SERVER['REQUEST_URI'], 1, strpos($_SERVER['REQUEST_URI'], '?') - 1));
    } else {
      $this->segments = explode("/", substr($_SERVER['REQUEST_URI'], 1));
    }
    $this->query = json_decode(json_encode($_GET), FALSE);
    $body = file_get_contents("php://input");
    $this->body = json_decode($body, FALSE);
    // !SECTION: SEGMENTS

    date_default_timezone_set($this->config->timezone ?? 'Europe/Madrid');

    // SECTION: IDIOMA
    $languages_long = $this->config->languages;
    $languages_short = [];

    for ($i = 0; $i < count($languages_long); $i++) {
      array_push($languages_short, substr($languages_long[$i], 0, 2));
    }

    $language_long = str_replace('-', '_', substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 5));
    $language_short = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);

    if (strlen($this->segments[0]) === 2 && in_array($this->segments[0], $languages_short)) {
      $language_short = $this->segments[0];
      for ($i = 0; $i < count($languages_long); $i++) {
        if (substr($languages_long[$i], 0, 2)) {
          $language_long = $languages_long[$i];
        }
      }
    }

    if (!in_array($language_short, $languages_short)) {
      $language_short = $languages_short[0];
      $language_long = $languages_long[0];
    }
    if ($language_short !== $this->segments[0]) {
      header('Location: /' . $language_short . ($_SERVER['REQUEST_URI'] === '/' ? '' : $_SERVER['REQUEST_URI']));
      exit();
    }
    setlocale(LC_ALL, $language_long . '@euro', $language_long . '.UTF-8', $language_short);
    $this->language_short = $language_short;
    $this->language_long = $language_long;
    // !SECTION: IDIOMA

    // SECTION: CACHE
    if (isset($this->config->cache->enabled)) {
      $almresponse = isset($this->query->almresponse) ?  $this->query->almresponse : 'default';
      $extension = 'html';
      $url = $_SERVER['REQUEST_URI'];
      $file = implode('|', explode('/', $url));
      $this->cacheFile = $this->rootDir . ($this->config->cache->directory ?? 'cache') . '/cached-' . $almresponse . '-' . $language_long . $file . '.' . $extension;
      $cachetime = $this->config->cache-> time ?? 18000;

      // Servimos de la cache si es menor que $cachetime
      if (file_exists($this->cacheFile) && time() - $cachetime < filemtime($this->cacheFile)) {
        echo "<!-- Cached copy, generated " . date('H:i', filemtime($this->cacheFile)) . " -->\n";
        readfile($this->cacheFile);
        $end = microtime(true);
        echo "<!--" . number_format($end - $this->start, 4) . "s-->";
        exit;
      }
      ob_start();
    }
    // !SECTION: CACHE

    if ($this->config->translate->enabled) {
      $directory = $this->rootDir . (isset($this->config->translate->directory) ? $this->config->translate->directory : 'i18n/');
      $findMissingTranslations = $this->config->translate->findMissingTranslations ? true : false;
      $this->translate = new \Almacil\Translate($this->language_short, $directory, $findMissingTranslations);
    }

    if ($this->config->database->enabled) {
      $directory = $this->rootDir . (isset($this->config->database->directory) ? $this->config->database->directory : 'data/');
      $extension = isset($this->config->database->extension) ? $this->config->database->extension : 'json';
      $this->database = new \Almacil\Database($directory, $extension);
    }

    if ($this->config->http->enabled) {
      $this->http = new \Almacil\Http($this->config->http->defaultPrefix);
    }

    if ($this->config->router->enabled) {
      $this->router = new \Almacil\Router($this->rootDir . $this->config->router->routesFile);
    }
  }

  public function translate($text, $params = null)
  {
    if ($this->config->translate->enabled) {
      return $this->translate->get($text, $params);
    } else {
      return '';
    }
  }

  public function set($key, $value)
  {
    return $this->data->{$key} = $value;
  }

  public function get($key)
  {
    return $this->data->{$key};
  }

  public function getLang($long = false)
  {
    return $long ? $this->language_long : $this->language_short;
  }

  public function getRootDir()
  {
    return $this->rootDir;
  }

  public function setHasError($value)
  {
    $this->hasError = $value;
  }

  public function getHasError()
  {
    return $this->hasError;
  }

  public function getObjError()
  {
    return $this->objError;
  }

  public function throw($code, $data)
  {
    $this->hasError = true;
    $this->objError = new \stdClass();
    $this->objError->code = $code;
    $this->objError->data = $data;
  }

  public function getCacheDir()
  {
    return $this->config->cache->directory;
  }

  public function getCacheFile()
  {
    return $this->cacheFile;
  }

  public function getStartMicrotime()
  {
    return $this->start;
  }

  public function getConfig()
  {
    return $this->config;
  }

  public function getCurrentRoute()
  {
    return substr(implode('/', $this->getSegments()), 2);
  }

  public function getSegments()
  {
    return $this->segments;
  }

  public function getQuery() {
    return $this->query;
  }

  public function getBody() {
    return $this->body;
  }

  public function getEnvironmentConfiguration()
  {
    return $this->environment;
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
}
