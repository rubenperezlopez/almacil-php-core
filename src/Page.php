<?php

/**
 * App.php
 *
 *
 * @category   App
 * @author     Rubén Pérez López
 * @date       10/02/2022
 * @copyright  2022 Rubén Pérez López
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    10/02/2022 v1.2.0
 * @link       www.rubenperezlopez.com
 */

namespace Almacil;

class Page
{

  protected $elementId;

  public function print(&$app)
  {

    if (!isset($this->pageConfig['elementId'])) {
      $this->pageConfig['elementId'] = $this->getRandomString();
    }
    $this->elementId = $this->pageConfig['elementId'];

    $arrayKeys = array_keys(get_object_vars($this));
    for ($i = 0; $i < count($arrayKeys); $i++) {
      ${$arrayKeys[$i]} = $this->{$arrayKeys[$i]};
    }

    include(__DIR__ . '/page.template.php');
  }

  private function getRandomString($length = 10)
  {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  private function getFilesRoute(&$app, $files)
  {
    $rootDir = $app->getRootDir() ?? '';

    if (gettype($files) === 'string') {
      $files = [$files];
    }

    for ($i = 0; $i < count($files); $i++) {
      $file = $rootDir . $files[$i];

      if ((file_exists($file) && !is_dir($file)) || (file_exists($file . '.php') && !is_dir($file . '.php'))) {

        // Es un único archivo
        if (file_exists($file)) {
          return $file;
        } else {
          return $file . '.php';
        }
      }
    }
  }
}
