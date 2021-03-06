
<?php

function t($text, $params = null)
{
  global $app;
  return $app->translate->get($text, $params);
}

if (count($app->router->getRequestAlmResponseType()) === 0) {

  echo '<!doctype html><html class="' . $this->pageConfig['htmlClass'] . '" lang="' . strtolower($app->getLang()) . '">';

  include($this->getFilesRoute($app, $app->router->getRoute()->head ?? $app->router->getConfig()->default->head));

  echo '<body class="' . $this->pageConfig['bodyClass'] . '">';
}

if ($app->router->getSpaEnabled($app, $app->router->getRoute()) && count($app->router->getRequestAlmResponseType()) === 0) {
  echo '<script>' . $app->router->getPageClassScript($app) . '</script>';
  echo '<script>' . $app->router->getComponentClassScript($app) . '</script>';
}

if ($app->router->hasRequestAlmResponseType(['', 'body', 'before'])) {

  if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
    echo '<div id="alm-before">';
  }

  include($this->getFilesRoute($app, $app->router->getRoute()->beforeContent ?? $app->router->getConfig()->default->beforeContent));

  if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
    echo '</div>';
  }
}

if ($this->pageConfig['templateUrl'] != '' || (isset($this->pageConfig['styleUrls']) && count($this->pageConfig['styleUrls']) > 0)) {
  if ($app->router->hasRequestAlmResponseType(['', 'body', 'content'])) {

    if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
      echo '<div id="alm-content">';
    }

    if (
      isset($this->pageConfig['styleUrls']) &&
      count($this->pageConfig['styleUrls']) > 0
    ) {
      foreach ($this->pageConfig['styleUrls'] as $styleUrl) {
        if (strpos($styleUrl, '.') === 0 ? file_exists($this->pageConfig['directory'] . '/' . $styleUrl) : false) {
          include($this->pageConfig['directory'] . '/' . $styleUrl);
        } else if (file_exists($styleUrl)) {
          include($styleUrl);
        }
      }
    }

    if (
      $this->pageConfig['templateUrl'] != '' &&
      file_exists($this->pageConfig['directory'] . '/' . $this->pageConfig['templateUrl'])
    ) {
      echo '<div id="' . $this->pageConfig['elementId'] . '">';
      include($this->pageConfig['directory'] . '/' . $this->pageConfig['templateUrl']);
      echo '</div>';
    }

    if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
      echo '</div>';
    }
  }
}


if ($app->router->hasRequestAlmResponseType(['', 'body', 'after'])) {

  if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
    echo '<div id="alm-after">';
  }

  include($this->getFilesRoute($app, $app->router->getRoute()->afterContent ?? $app->router->getConfig()->default->afterContent));

  if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
    echo '</div>';
  }
}

if ($app->router->getSpaEnabled($app, $app->router->getRoute()) && count($app->router->getRequestAlmResponseType()) === 0) {
  echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>';
  // array_push($_itemsToInclude, $this->getHtmlItemObject('<script src="http://localhost:8001/third/jquery.min.js"></script>'));
  echo '<script>' . $app->router->getCaptainScript($app) . '</script>';
}

if (isset($this->pageConfig['scriptUrls']) && count($this->pageConfig['scriptUrls']) > 0) {
  if ($app->router->hasRequestAlmResponseType(['', 'body', 'js'])) {

    if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
      echo '<div id="alm-js">';
    }

    foreach ($this->pageConfig['scriptUrls'] as $scriptUrl) {
      if (strpos($scriptUrl, '.') === 0 ? file_exists($this->pageConfig['directory'] . '/' . $scriptUrl) : false) {
        include($this->pageConfig['directory'] . '/' . $scriptUrl);
      } else if (file_exists($scriptUrl)) {
        include($scriptUrl);
      }
    }

    if ($app->router->getSpaEnabled($app, $app->router->getRoute())) {
      echo '</div>';
    }
  }
}

if (count($app->router->getRequestAlmResponseType()) === 0) {
  echo '</body></html>';
}
