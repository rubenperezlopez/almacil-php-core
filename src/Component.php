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

class Component
{
  public function print()
  {
    if ($this->componentConfig['templateUrl'] === './header.html.php') {
    // echo $this->componentConfig['templateUrl'];exit();
    }
    /*function t($text, $params = null)
    {
      global $app;
      return $app->translate->get($text, $params);
    }*/

    $arrayKeys = array_keys(get_object_vars($this));
    for ($i = 0; $i < count($arrayKeys); $i++) {
      ${$arrayKeys[$i]} = $this->{$arrayKeys[$i]};
    }

    if ($this->componentConfig['templateUrl'] != '' || (isset($this->componentConfig['styleUrls']) && count($this->componentConfig['styleUrls']) > 0)) {
      if (
        isset($this->componentConfig['styleUrls']) &&
        count($this->componentConfig['styleUrls']) > 0
      ) {
        foreach ($this->componentConfig['styleUrls'] as $styleUrl) {
          if (file_exists($this->componentConfig['directory'] . '/' . $styleUrl)) {
            include($this->componentConfig['directory'] . '/' . $styleUrl);
          }
        }
      }
      if (
        $this->componentConfig['templateUrl'] != '' &&
        file_exists($this->componentConfig['directory'] . '/' . $this->componentConfig['templateUrl'])
      ) {
        include($this->componentConfig['directory'] . '/' . $this->componentConfig['templateUrl']);
      }
    }

    if (isset($this->componentConfig['scriptUrls']) && count($this->componentConfig['scriptUrls']) > 0) {
      foreach ($this->componentConfig['scriptUrls'] as $scriptUrl) {
        if (file_exists($this->componentConfig['directory'] . '/' . $scriptUrl)) {
          include($this->componentConfig['directory'] . '/' . $scriptUrl);
        }
      }
    }
  }
}
