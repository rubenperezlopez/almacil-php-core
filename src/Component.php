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

  protected $elementId;

  public function print()
  {

    if (!isset($this->componentConfig['elementId'])) {
      $this->componentConfig['elementId'] = $this->getRandomString();
    }
    $this->elementId = $this->componentConfig['elementId'];

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
          if (strpos($styleUrl, '.') === 0 ? file_exists($this->componentConfig['directory'] . '/' . $styleUrl) : false) {
            include($this->componentConfig['directory'] . '/' . $styleUrl);
          } else if (file_exists($styleUrl)) {
            include($styleUrl);
          }
        }
      }
      if (
        $this->componentConfig['templateUrl'] != '' &&
        file_exists($this->componentConfig['directory'] . '/' . $this->componentConfig['templateUrl'])
      ) {
        echo '<div id="' . $this->componentConfig['elementId'] . '">';
        include($this->componentConfig['directory'] . '/' . $this->componentConfig['templateUrl']);
        echo '</div>';
      }
    }

    if (isset($this->componentConfig['scriptUrls']) && count($this->componentConfig['scriptUrls']) > 0) {
      foreach ($this->componentConfig['scriptUrls'] as $scriptUrl) {
        if (strpos($scriptUrl, '.') === 0 ? file_exists($this->componentConfig['directory'] . '/' . $scriptUrl) : false) {
          include($this->componentConfig['directory'] . '/' . $scriptUrl);
        } else if (file_exists($scriptUrl)) {
          include($scriptUrl);
        }
      }
    }
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
}
