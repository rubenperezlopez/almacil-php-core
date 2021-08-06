<?php

/**
 * Router.php
 *
 *
 * @category   Router
 * @author     Rubén Pérez López
 * @date       04/08/2021
 * @copyright  2021 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    04/08/2021 v1.0
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Router
{
  private $config;
  private $router;

  private $segments;

  public function __construct($routesFile = __DIR__ . '/routes.json')
  {
    $this->config = $this->getFile($routesFile);
  }

  public function getSegments()
  {
    return $this->segments;
  }

  public function run($app)
  {
    $this->router = new \Bramus\Router\Router();

    $this->segments = $app->getSegments();

    $this->router->set404(function () {
      header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
      $this->send('404, route not found! ' . $_SERVER['REQUEST_METHOD']);
    });

    for ($r = 0; $r < count($this->config->routes); $r++) {
      $route = $this->config->routes[$r];
      $method = ($route->method ?? $this->config->default->method) ?? 'GET';

      $this->router->{$method}($route->path, function () use ($app, $route) {
        $_itemsToInclude = [];
        if (($route->responseType ?? $this->config->default->responseType) === 'html') {

          header('Content-type: text/html; charset=UTF-8');

          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $this->config->middlewares ?? [], $route));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->middlewares ?? $this->config->default->middlewares ?? [], $route));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, [$route->component], $route));
        } else {

          header('Access-Control-Allow-Origin: *');
          header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
          header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Action, User-Id, Session-Id, Device-Id, Cache-Hash");
          header('Content-Type: application/json');
          header('X-Powered-By: Almacil');

          if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit();
          }

          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $this->config->middlewares ?? [], $route));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->middlewares ?? $this->config->default->middlewares ?? [], $route));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, [$route->controller], $route));
        }

        for ($_f = 0; $_f < count($_itemsToInclude); $_f++) {
          $_item = $_itemsToInclude[$_f];
          if ($_item->type === 'file') {
            include($_item->file);
          } else if ($_item->type === 'html') {
            echo $_item->html;
          }
        }

        // SECTION: CACHE
        if ($this->getCacheIsEnabled($app, $route)) {
          // Crea el archivo de cache
          $end = microtime(true);
          echo "<!--" . number_format($end - $app->getStartMicrotime(), 4) . "s-->";
          $cached = fopen($app->getCacheFile(), 'w');
          fwrite($cached, ob_get_contents());
          fclose($cached);
          ob_end_flush(); // Envia la salida al navegador
        }
        // !SECTION: CACHE

      });
    }

    $this->router->run();

    return $this->router;
  }

  private function getCacheIsEnabled($app, $route) {
    if (($route->responseType ?? $this->config->default->responseType) !== 'html') {
      return false;
    }
    $globalCacheEnabled = isset($app->getConfig()->cache->enabled) ? $app->getConfig()->cache->enabled : false;
    $defaultCacheEnabled = isset($this->config->default->cache->enabled) ? $this->config->default->cache->enabled : $globalCacheEnabled;
    $routeCacheEnabled = isset($route->cache->enabled) ? $route->cache->enabled : $defaultCacheEnabled;

    return $routeCacheEnabled;
  }

  private function includeFiles($app, $files = [], $route = null)
  {
    $rootDir = $app->getRootDir() ?? '';
    $_itemsToInclude = [];

    if (gettype($files) === 'string') {
      $files = [$files];
    }

    for ($i = 0; $i < count($files); $i++) {
      $file = $rootDir . $files[$i];

      if ((file_exists($file) && !is_dir($file)) || (file_exists($file . '.php') && !is_dir($file . '.php'))) {

        // Es un único archivo
        if (file_exists($file)) {
          array_push($_itemsToInclude, $this->getFileItemObject($file));
        } else {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.php'));
        }
      } else {

        // Si es un directorio sacamos el último segmento del path y se lo añadimos a $file
        if (is_dir($file)) {
          $segments = explode('/', $file);
          $lastSegment = $segments[count($segments) - 1];
          $file = $file . '/' . $lastSegment;
        }

        // Incluímos los archivos que soporta el framework si existen
        if (file_exists($file . '.controller.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.controller.php'));
        }

        if ($route) {
          array_push($_itemsToInclude, $this->getHtmlItemObject('<!doctype html><html lang="' . strtolower($app->getLang()) . '">'));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->head ?? $this->config->default->head));
          array_push($_itemsToInclude, $this->getHtmlItemObject('<body>'));
        }

        if ($route) {
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->beforeContent ?? $this->config->default->beforeContent));
        }

        if (file_exists($file . '.css.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.css.php'));
        }

        if (file_exists($file . '.html.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.html.php'));
        }

        if ($route) {
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->afterContent ?? $this->config->default->afterContent));
        }

        if (file_exists($file . '.js.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.js.php'));
        }

        array_push($_itemsToInclude, $this->getHtmlItemObject('</body></html>'));
      }
    }
    return $_itemsToInclude;
  }


  public static function send($data = array(), $http_response_code = 200, $error_message = '', $continue = false, $extra = null)
  {
    $resp = new \stdClass();
    if (!is_null($data)) {
      $resp->data = $data;
    }
    http_response_code($http_response_code == '' ? 200 : $http_response_code);
    if ((string)$error_message != '') {
      $resp->error = $error_message;
    }

    if ($extra) {
      $resp->extra = $extra;
    }

    echo json_encode($resp, JSON_PRETTY_PRINT);

    if (!$continue) {
      exit();
    }
  }

  private function getFileItemObject($file)
  {
    $item = new \stdClass();
    $item->type = 'file';
    $item->file = $file;
    return $item;
  }

  private function getHtmlItemObject($html)
  {
    $item = new \stdClass();
    $item->type = 'html';
    $item->html = $html;
    return $item;
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
