<?php

/**
 * @file
 * Contains \Drupal\date_api\Element\DateElementBase.
 */

namespace Drupal\date_api\Render\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\date_api\DateObject;

/**
 * Provides a base class for date elements.
 */
abstract class DateApiElementBase extends FormElement {



  /**
   * Create a date object from a datetime string value.
   */
  public static function getDefaultDate($element) {
    $granularity = date_format_order($element['#date_format']);
    $default_value = $element['#default_value'];
    $format = DATE_FORMAT_DATETIME;

    // The text and popup widgets might return less than a full datetime string.
    if (strlen($element['#default_value']) < 19) {
      switch (strlen($element['#default_value'])) {
        case 16:
          $format = 'Y-m-d H:i';
          break;
        case 13:
          $format = 'Y-m-d H';
          break;
        case 10:
          $format = 'Y-m-d';
          break;
        case 7:
          $format = 'Y-m';
          break;
        case 4:
          $format = 'Y';
          break;
      }
    }
    $date = new DateObject($default_value, $element['#date_timezone'], $format);
    if (is_object($date)) {
      $date->limitGranularity($granularity);
      if ($date->validGranularity($granularity, $element['#date_flexible'])) {
        date_increment_round($date, $element['#date_increment']);
      }
      return $date;
    }
    return NULL;
  }

}
