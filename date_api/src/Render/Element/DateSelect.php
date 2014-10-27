<?php

/**
 * @file
 * Contains \Drupal\date_api\Render\Element\DateSelect.
 */

namespace Drupal\date_api\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\date_api\DateObject;
use Drupal\date_api\DateApiManager;

/**
 * Provides a one-line text field form element.
 *
 * @FormElement("date_select")
 */
class DateSelect extends DateApiElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#tree' => TRUE,
      '#date_timezone' => DateApiManager::date_default_timezone(),
      '#date_flexible' => 0,
      '#date_format' => \Drupal::config('core.date_formats.short')->get('pattern', 'm/d/Y - H:i'),
      '#date_text_parts' => array(),
      '#date_increment' => 1,
      '#date_year_range' => '-3:+3',
      '#date_label_position' => 'above',
      '#theme_wrappers' => array('date_select'),
      '#process' => array($class, 'process'),
      '#value_callback' => array($class, 'valueCallback'),

    );
    // @TODO: Check what ctools come up with.
    // if (module_exists('ctools')) {
    // $date_base['#pre_render'] = array('ctools_dependent_pre_render');
    // }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $return = array('year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => '');
    $date = NULL;
    if ($input !== FALSE) {
      $return = $input;
      $date = self::selectInputDate($element, $input);
    }
    elseif (!empty($element['#default_value'])) {
      $date = self::getDefaultDate($element);
    }
    $granularity = DateApiManager::date_format_order($element['#date_format']);
    $formats = array('year' => 'Y', 'month' => 'n', 'day' => 'j', 'hour' => 'H', 'minute' => 'i', 'second' => 's');
    foreach ($granularity as $field) {
      if ($field != 'timezone') {
        $return[$field] = DateApiManager::date_is_date($date) ? $date->format($formats[$field]) : '';
      }
    }
    return $return;
  }

  /**
   * Process an individual date element.
   */
  protected function process(&$element, &$form_state, $form) {
    if (DateApiManager::date_hidden_element($element)) {
      return $element;
    }

    $date = NULL;
    DateApiManager::date_format_order($element['#date_format']);

    if (is_array($element['#default_value'])) {
      $date = self::selectInputDate($element, $element['#default_value']);
    }
    elseif (!empty($element['#default_value'])) {
      $date = $this->getDefaultDate($element);
    }

    $element['#tree'] = TRUE;
    $element['#theme_wrappers'] = array('date_select');

    $element += (array) date_parts_element($element, $date, $element['#date_format']);

    // Store a hidden value for all date parts not in the current display.
    $granularity = DateApiManager::date_format_order($element['#date_format']);
    foreach (DateApiManager::date_nongranularity($granularity) as $field) {
      if ($field != 'timezone') {
        $element[$field] = array(
          '#type' => 'value',
          '#value' => 0,
        );
      }
    }
    if (isset($element['#element_validate'])) {
      array_push($element['#element_validate'], 'date_select_validate');
    }
    else {
      $element['#element_validate'] = array('date_select_validate');
    }

    $context = array(
      'form' => $form,
    );

    \Drupal::moduleHandler()->alter('date_select_process', $element, $form_state, $context);
  }

  /**
   * Helper function for creating a date object out of user input.
   */
  public static function selectInputDate($element, $input) {

    // Was anything entered? If not, we have no date.
    if (!is_array($input)) {
      return NULL;
    }
    else {
      $entered = array_values(array_filter($input));
      if (empty($entered)) {
        return NULL;
      }
    }
    $granularity = DateApiManager::date_format_order($element['#date_format']);
    if (isset($input['ampm'])) {
      if ($input['ampm'] == 'pm' && $input['hour'] < 12) {
        $input['hour'] += 12;
      }
      elseif ($input['ampm'] == 'am' && $input['hour'] == 12) {
        $input['hour'] -= 12;
      }
    }
    unset($input['ampm']);

    // Make the input match the granularity.
    foreach (DateApiManager::date_nongranularity($granularity) as $part) {
      unset($input[$part]);
    }

    $date = new DateObject($input, $element['#date_timezone']);
    if (is_object($date)) {
      $date->limitGranularity($granularity);
      if ($date->validGranularity($granularity, $element['#date_flexible'])) {
        DateApiManager::date_increment_round($date, $element['#date_increment']);
      }
      return $date;
    }
    return NULL;
  }
  
}
