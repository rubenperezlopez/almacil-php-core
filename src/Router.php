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
  private $router;

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

  public function run($app)
  {
    $this->router = new \Bramus\Router\Router();

    $this->segments = $app->getSegments();
    $this->query = $app->getQuery();
    $this->body = $app->getBody();

    $this->router->set404(function () {
      header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
      $this->send('404, route not found! ' . $_SERVER['REQUEST_METHOD']);
    });

    $lang = $app->getLang();
    $this->router->setBasePath('/' . $lang);

    $this->config->routes = $this->orderRoutes($this->config->routes);

    for ($r = 0; $r < count($this->config->routes); $r++) {
      $route = $this->config->routes[$r];
      $method = ($route->method ?? $this->config->default->method) ?? 'GET';

      $this->router->{$method}("$route->path", function () use ($app, $route) {
        $_itemsToInclude = [];
        $_response = isset($this->query->response) ?  $this->query->response : '';

        $route->params = $this->getRouteParams($app, $route);

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

        for ($_f = 0; $_f < count($_itemsToInclude); $_f++) {
          $_item = $_itemsToInclude[$_f];
          if ($_item->type === 'file') {
            include($_item->file);
          } else if ($_item->type === 'html') {
            echo $_item->html;
          } else if ($_item->type === 'js') {
            echo '<script>' . $_item->code . '</script>';
          }
        }

        // SECTION: CACHE
        if ($this->getCacheIsEnabled($app, $route)) {
          // Crea el archivo de cache
          $end = microtime(true);
          if (($route->responseType ?? $this->config->default->responseType) === 'html') {
            echo "<!--" . number_format($end - $app->getStartMicrotime(), 4) . "s-->";
          }
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

  public function getRoute() {
    return $this->route;
  }

  private function getRouteParams($app, $route)
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

  private function orderRoutes($routes) {
    $newRoutes = [];
    $arrNumSegments = [];
    for ($r = 0; $r < count($routes); $r++) {
      $route = $routes[$r];
      $numSegments = count(explode('/', $route->path));
      $route->numSegments = $numSegments;
      if (!in_array($numSegments, $arrNumSegments)) {
        array_push($arrNumSegments, $numSegments);
      }
    }
    rsort($arrNumSegments);
    for ($a = 0; $a < count($arrNumSegments); $a++) {
      for ($r = 0; $r < count($routes); $r++) {
        $route = $routes[$r];
        if (count(explode('/', $route->path)) === $arrNumSegments[$a]) {
          array_push($newRoutes, $route);
        }
      }
    }
    return $newRoutes;
  }

  private function getRequestAlmResponseType()
  {
    $almresponseArray = isset($this->query->almresponse) ? explode(',', $this->query->almresponse) : [];
    return $almresponseArray;
  }

  private function hasRequestAlmResponseType($sections)
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

  private function getSpaEnabled($app, $route)
  {
    if (($route->responseType ?? $this->config->default->responseType) !== 'html') {
      return false;
    }
    $globalSpaEnabled = isset($app->getConfig()->spa->enabled) ? $app->getConfig()->spa->enabled : false;

    return $globalSpaEnabled;
  }

  private function getCacheIsEnabled($app, $route)
  {
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
              array_push($_itemsToInclude, $this->getFileItemObject($file . '.css.php'));
            }
            if (file_exists($file . '.html.php')) {
              array_push($_itemsToInclude, $this->getFileItemObject($file . '.html.php'));
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

  private function getFileItemObject($file)
  {
    $item = new \stdClass();
    $item->type = 'file';
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

  private function getCaptainScript($app)
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
                  console.log(el.id, el);
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
}
