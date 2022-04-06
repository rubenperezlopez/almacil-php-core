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
 * @version    24/08/2021 v1.0.5
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Router
{
  private $config;

  private $segments;
  private $query;
  private $body;

  private $route;

  public function __construct($routesFile = __DIR__ . '/routes.json')
  {
    $this->config = $this->getFile($routesFile);
  }

  public function getSegments()
  {
    return $this->segments;
  }

  public function getConfig()
  {
    return $this->config;
  }

  public function run(&$app)
  {

    $this->segments = $app->getSegments();
    $this->query = $app->getQuery();
    $this->body = $app->getBody();

    $app->setHasError(false);

    $lang = $app->getLang();

    $urlPath = str_replace('/' . $lang, '', $_SERVER['REQUEST_URI']);
    if (strpos($urlPath, '?') > 0) {
      $urlPath = substr($urlPath, 0, strpos($urlPath, '?'));
    }

    $this->config->routes = $this->orderRoutes($this->config->routes);

    for ($r = 0; $r < count($this->config->routes); $r++) {
      $route = $this->config->routes[$r];
      $method = ($route->method ?? $this->config->default->method) ?? 'GET';

      if (
        $this->matchRoute($urlPath, $route->path) &&
        ($_SERVER['REQUEST_METHOD'] === $method || $_SERVER['REQUEST_METHOD'] === 'OPTIONS')
      ) {

        $route->params = $this->getRouteParams($app, $route);
        $this->route = $route;

        if (strpos($route->component, '#') > 0) {
          $this->printRouteClasses($app, $route);
        } else {
          $this->printRouteIncludes($app, $route);
        }

        // SECTION: CACHE
        if ($this->getCacheIsEnabled($app, $route)) {

          // Crea el archivo de cache
          $appConfig = $app->getConfig();
          $expireTime = time() + (int)($route->cache->time ?? $appConfig->cache->time ?? 60);
          $end = microtime(true);

          if (($route->responseType ?? $this->config->default->responseType) === 'html') {
            echo "<!--" . number_format($end - $app->getStartMicrotime(), 4) . "s-->";
            echo "<!--" . date('d/m/Y H:i:s', $expireTime) . " " . (int)($route->cache->time ?? $appConfig->cache->time ?? 60) . "s-->";
          }

          if ($appConfig->cache->type  === 'redis') {

            $redisClient = $app->getRedisClient();
            $cacheHash = md5($app->getCacheFile());

            $cachedb = json_decode($redisClient->get('cachedb') ?? 'null');
            if ($cachedb === null) {
              $cachedb = [];
            }
            $cacheObject = new \stdClass();
            $cacheObject->hash = $cacheHash;
            $cacheObject->expiretime = $expireTime;
            array_push($cachedb, $cacheObject);

            $redisClient->set('cachedb', json_encode($cachedb));
            $redisClient->set('cacheexpiretime-' . $cacheHash, $expireTime);
            $redisClient->set('cachefile-' . $cacheHash, ob_get_contents());
            $tem  = $redisClient->get('cacheexpiretime-' . $cacheHash);
          } else {
            $cached = fopen($app->getCacheFile(), 'w');
            fwrite($cached, ob_get_contents());
            fclose($cached);
          }

          ob_end_flush(); // Envia la salida al navegador
        }
        // !SECTION: CACHE

        return true;
      }
    }

    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    echo '404, route not found! ' . $_SERVER['REQUEST_METHOD'];
  }

  public function getRoute()
  {
    return $this->route;
  }

  private function printRouteClasses(&$app, $route)
  {
    $componentDirectory = $this->getComponentDirectory($route->component);
    $componentClassName = $this->getComponentClassName($route->component);
    $componentDirectorySegments = explode('/', $componentDirectory);

    $rootDir = $app->getRootDir() ?? '';
    if (!file_exists($rootDir . '/' . $componentDirectory . '/' .
      $componentDirectorySegments[count($componentDirectorySegments) - 1] . '.controller.php')) {
      echo 'File not found: ' . $rootDir . '/' . $componentDirectory . '/' .
        $componentDirectorySegments[count($componentDirectorySegments) - 1] . '.controller.php';
      exit();
    }
    require $rootDir . '/' . $componentDirectory . '/' .
      $componentDirectorySegments[count($componentDirectorySegments) - 1] . '.controller.php';

    $componentClass = new $componentClassName($app);
    $componentClass->print($app);
  }

  private function getComponentClassName($component)
  {
    return substr($component, strpos($component, '#') + 1);
  }

  private function getComponentDirectory($component)
  {
    return substr($component, 0, strpos($component, '#'));
  }

  private function printRouteIncludes(&$app, $route)
  {
    $_itemsToInclude = [];
    $_response = isset($this->query->response) ?  $this->query->response : '';

    if ($_response === 'content') {
      header('Content-type: text/html; charset=UTF-8');
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $this->config->middlewares ?? []));
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->middlewares ?? $this->config->default->middlewares ?? []));
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, [$route->component], $route));
    } else if ($_response === 'js') {
      header('Content-type: text/javascript; charset=UTF-8');
    } else if (($route->responseType ?? $this->config->default->responseType) === 'html') {
      header('Content-type: text/html; charset=UTF-8');

      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $this->config->middlewares ?? []));
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->middlewares ?? $this->config->default->middlewares ?? []));
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

      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $this->config->middlewares ?? []));
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->middlewares ?? $this->config->default->middlewares ?? []));
      $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, [$route->controller], $route));
    }

    unset($route->numSegments);
    $this->route = $route;

    $_errorShown = false;
    for ($_f = 0; $_f < count($_itemsToInclude); $_f++) {
      $_item = $_itemsToInclude[$_f];
      if ($_item->type === 'file') {
        if ($app->getHasError() && $_item->section === 'content') {
          if (!$_errorShown) {
            $_errorShown = true;
            $_rootDir = $app->getRootDir() ?? '';
            $_file = $_rootDir . ($route->throw ?? $this->config->default->throw);
            if ((file_exists($_file) && !is_dir($_file)) || (file_exists($_file . '.php') && !is_dir($_file . '.php'))) {
              if (file_exists($_file)) {
                include($_file);
              } else {
                include($_file . '.php');
              }
            }
          }
        } else {
          include($_item->file);
        }
      } else if ($_item->type === 'html') {
        echo $_item->html;
      } else if ($_item->type === 'js') {
        echo '<script>' . $_item->code . '</script>';
      }
    }
  }

  private function getRouteParams(&$app, $route)
  {
    $params = new \stdClass();
    $routeSegments = explode('/', $route->path);
    $urlSegments = $app->getSegments();
    for ($s = 0; $s < count($routeSegments); $s++) {
      if (substr($routeSegments[$s], 0, 1) === '{') {
        $varName = substr($routeSegments[$s], 1, strlen($routeSegments[$s]) - 2);
        $params->$varName = $urlSegments[$s];
      }
    }
    return $params;
  }

  private function orderRoutes($routes)
  {
    $newRoutes = [];
    $arrNumSegments = [];
    $arrNumRank = [];
    $maxNumSegments = 0;
    for ($r = 0; $r < count($routes); $r++) {
      $route = $routes[$r];
      $numSegments = count(explode('/', $route->path));
      $route->numSegments = $numSegments;
      if (!in_array($numSegments, $arrNumSegments)) {
        array_push($arrNumSegments, $numSegments);
      }
      if ($maxNumSegments < $numSegments) {
        $maxNumSegments = $numSegments;
      }
    }
    rsort($arrNumSegments);
    for ($r = 0; $r < count($routes); $r++) {
      $route = $routes[$r];
      $segments = explode('/', $route->path);
      $strRank = '';
      for ($s = 0; $s < $route->numSegments; $s++) {
        if ($segments[$s] != '') {
          $segment = $segments[$s];
          $strRank .= substr($segment, 0, 1) === '{' ? '1' : '2';
        } else {
          $strRank .= '0';
        }
      }
      $strRank .= $route->numSegments;
      $route->rank = (int)$strRank;
      if (!in_array($route->rank, $arrNumRank)) {
        array_push($arrNumRank, $route->rank);
      }
    }
    rsort($arrNumRank);
    for ($a = 0; $a < count($arrNumRank); $a++) {
      for ($r = 0; $r < count($routes); $r++) {
        $route = $routes[$r];
        if ($route->rank === $arrNumRank[$a]) {
          array_push($newRoutes, $route);
        }
      }
    }
    return $newRoutes;
  }

  private function matchRoute($urlPath, $routePath)
  {
    $urlSegments = explode('/', $urlPath);
    $routeSegments = explode('/', $routePath);

    if (count($urlSegments) !== count($routeSegments)) {
      return false;
    }

    $segmentsMatch = 0;
    for ($i = 0; $i < count($urlSegments); $i++) {
      $urlSegment = $urlSegments[$i];
      $routeSegment = $routeSegments[$i];
      if ($urlSegment === $routeSegment || substr($routeSegment, 0, 1) === '{') {
        $segmentsMatch++;
      }
    }

    return $segmentsMatch === count($urlSegments);
  }

  public function getRequestAlmResponseType()
  {
    $almresponseArray = isset($this->query->almresponse) ? explode(',', $this->query->almresponse) : [];
    return $almresponseArray;
  }

  public function hasRequestAlmResponseType($sections)
  {
    $almresponseArray = $this->getRequestAlmResponseType();
    for ($a = 0; $a < count($sections); $a++) {
      if ($sections[$a] != '') {
        if (in_array($sections[$a], $almresponseArray)) {
          return true;
        }
      } else if (count($almresponseArray) === 0) {
        return true;
      }
    }
    return false;
  }

  public function getSpaEnabled(&$app, $route)
  {
    if (($route->responseType ?? $this->config->default->responseType) !== 'html') {
      return false;
    }
    $globalSpaEnabled = isset($app->getConfig()->spa->enabled) ? $app->getConfig()->spa->enabled : false;

    return $globalSpaEnabled;
  }

  private function getCacheIsEnabled(&$app, $route)
  {
    if (($route->responseType ?? $this->config->default->responseType) !== 'html') {
      return false;
    }
    $globalCacheEnabled = isset($app->getConfig()->cache->enabled) ? $app->getConfig()->cache->enabled : false;
    $defaultCacheEnabled = isset($this->config->default->cache->enabled) ? $this->config->default->cache->enabled : $globalCacheEnabled;
    $routeCacheEnabled = isset($route->cache->enabled) ? $route->cache->enabled : $defaultCacheEnabled;

    return $routeCacheEnabled;
  }

  private function includeFiles(&$app, $files = [], $route = null)
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
        if (file_exists($file . '.service.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.service.php'));
        }

        if (file_exists($file . '.middleware.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.middleware.php'));
        }

        if (file_exists($file . '.controller.php')) {
          array_push($_itemsToInclude, $this->getFileItemObject($file . '.controller.php'));
        }

        if ($route && count($this->getRequestAlmResponseType()) === 0) {
          array_push($_itemsToInclude, $this->getHtmlItemObject('<!doctype html><html lang="' . strtolower($app->getLang()) . '">'));
          $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->head ?? $this->config->default->head));
          array_push($_itemsToInclude, $this->getHtmlItemObject('<body>'));
        }

        if ($route) {
          if ($this->hasRequestAlmResponseType(['', 'body', 'before'])) {
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('<div id="alm-before">'));
            }
            $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->beforeContent ?? $this->config->default->beforeContent));
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('</div>'));
            }
          }
        }

        if (file_exists($file . '.css.php') || file_exists($file . '.html.php')) {
          if ($this->hasRequestAlmResponseType(['', 'body', 'content'])) {
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('<div id="alm-content">'));
            }
            if (file_exists($file . '.css.php')) {
              array_push($_itemsToInclude, $this->getFileItemObject($file . '.css.php', 'content'));
            }
            if (file_exists($file . '.html.php')) {
              array_push($_itemsToInclude, $this->getFileItemObject($file . '.html.php', 'content'));
            }
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('</div>'));
            }
          }
        }

        if ($route) {
          if ($this->hasRequestAlmResponseType(['', 'body', 'after'])) {
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('<div id="alm-after">'));
            }
            $_itemsToInclude = array_merge($_itemsToInclude, $this->includeFiles($app, $route->afterContent ?? $this->config->default->afterContent));
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('</div>'));
            }
          }
        }

        if ($this->getSpaEnabled($app, $route) && count($this->getRequestAlmResponseType()) === 0) {
          array_push($_itemsToInclude, $this->getHtmlItemObject('<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>'));
          // array_push($_itemsToInclude, $this->getHtmlItemObject('<script src="http://localhost:8001/third/jquery.min.js"></script>'));
          array_push($_itemsToInclude, $this->getJavaScriptItemObject($this->getCaptainScript($app)));
        }

        if (file_exists($file . '.js.php')) {
          if ($this->hasRequestAlmResponseType(['', 'body', 'js'])) {
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('<div id="alm-js">'));
            }
            array_push($_itemsToInclude, $this->getFileItemObject($file . '.js.php'));
            if ($this->getSpaEnabled($app, $route)) {
              array_push($_itemsToInclude, $this->getHtmlItemObject('</div>'));
            }
          }
        }

        if ($route && count($this->getRequestAlmResponseType()) === 0) {
          array_push($_itemsToInclude, $this->getHtmlItemObject('</body></html>'));
        }
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

  private function getFileItemObject($file, $section = 'layout')
  {
    $item = new \stdClass();
    $item->type = 'file';
    $item->section = $section;
    $item->file = $file;
    return $item;
  }

  private function getJavaScriptItemObject($code)
  {
    $item = new \stdClass();
    $item->type = 'js';
    $item->code = $code;
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

  public function getCaptainScript(&$app)
  {
    $lang = $app->getLang();
    $code = <<<EOD
    window.onPopStateSections = window.onPopStateSections || [];
    window.onpopstate = function(event) {
      const route = document.location.href;
      captain.navigate(route, window.onPopStateSections, true);
    }
    window.captain = (function() {
      const navigate = function(route, sections = [], fromPopState = false) {
        new Promise(function(resolve, reject) {
          const firstSlash = route.replace('https://', 'http://').replace('http://', '').indexOf('/');
          route = route.replace('https://', 'http://').replace('http://', '').substr(firstSlash);
          route = '/$lang' + route.replace('/$lang/', '/');
          const path = route;
          const almresponse = !sections.length ? 'body' : sections.join(',');
          if ((route || '').indexOf('?') > 0) {
            route += '&almresponse=' + almresponse;
          } else {
            route += '?almresponse=' + almresponse;
          }
          $.ajax(route)
            .done(function(response) {

              if (!fromPopState) {
                window.history.pushState("", "", path);
              }

              const html = $.parseHTML(response, null, true);
              $.each(html, function(i, el) {

                if (el.id === 'alm-before') {
                  if (!$('body #alm-before').length) {
                    $('body').prepend('<div id="alm-before"></div>');
                  }
                  $('body #alm-before').html(el.innerHTML);
                }

                if (el.id === 'alm-content') {
                  if (!$('body #alm-content').length) {
                    if ($('body #alm-before').length) {
                      $('body #alm-before').after('<div id="alm-content"></div>');
                    } else {
                      $('body').prepend('<div id="alm-content"></div>');
                    }
                  }
                  $('body #alm-content').html(el.innerHTML);
                }

                if (el.id === 'alm-after') {
                  if (!$('body #alm-after').length) {
                    if ($('body #alm-content').length) {
                      $('body #alm-content').after('<div id="alm-after"></div>');
                    } else if ($('body #alm-before').length) {
                      $('body #alm-before').after('<div id="alm-after"></div>');
                    } else {
                      $('body').prepend('<div id="alm-after"></div>');
                    }
                  }
                  $('body #alm-after').html(el.innerHTML);
                }

                if (el.id === 'alm-js') {
                  if (!$('body #alm-js').length) {
                    if ($('body #alm-after').length) {
                      $('body #alm-after').after('<div id="alm-js"></div>');
                    } else if ($('body #alm-content').length) {
                      $('body #alm-content').after('<div id="alm-js"></div>');
                    } else if ($('body #alm-before').length) {
                      $('body #alm-before').after('<div id="alm-js"></div>');
                    } else {
                      $('body').prepend('<div id="alm-js"></div>');
                    }
                  }
                  $('body #alm-js').html(el.innerHTML);
                }

              });
              resolve("success");
            })
            .fail(function() {
              reject("error");
            });
        });
      }
      return {
        navigate
      }
    })();
    EOD;
    return $code;
  }

  public function getPageClassScript(&$app)
  {
    $lang = $app->getLang();
    $code = <<<EOD
      class Page {
        constructor() {

          this.elementId = $('#alm-content div').first().attr('id');

          $('#alm-content').bind('DOMNodeRemoved', (e) => {
            var element = e.target;
            if (element.id == this.elementId && typeof this.onDestroy === 'function') {
              this.onDestroy();
            }
          });

        }
      }
    EOD;
    return $code;
  }

  public function getComponentClassScript(&$app)
  {
    $lang = $app->getLang();
    $code = <<<EOD
      class Component {
        constructor(elementId) {
          this._callbacks = {};

          this.elementId = elementId;
          if (this.elementId) {
            $('#' + this.elementId).bind('DOMNodeRemoved', (e) => {
              var element = e.target;
              if (element.id == this.elementId && typeof this.onDestroy === 'function') {
                this.onDestroy();
              }
            });
          }

        }

        addListener(callback, id = this.#randomString()) {
          this._callbacks[id] = callback;
          return id;
        }

        removeListener(id) {
          delete this._callbacks[id];
          return true;
        }

        emit(message) {
          const callbacks = this._callbacks || {};
          Object.keys(this._callbacks || {}).forEach((id) => {
            if (typeof callbacks[id] === 'function') {
              callbacks[id](message);
            }
          });
        }

        #randomString(len = 6) {
          let charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
          let randomString = '';
          for (let i = 0; i < len; i++) {
            let randomPoz = Math.floor(Math.random() * charSet.length);
            randomString += charSet.substring(randomPoz, randomPoz + 1);
          }
          charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
          return randomString;
        }
      }
    EOD;
    return $code;
  }
}
