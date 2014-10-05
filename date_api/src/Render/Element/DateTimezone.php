<?php

/**
 * @file
 * Contains \Drupal\date_api\Render\Element\DateTimezone.
 */

namespace Drupal\date_api\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a one-line text field form element.
 *
 * @FormElement("date_timezone")
 */
class DateTimezone extends DateApiElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#tree' => TRUE,
      '#date_timezone' => date_default_timezone(),
      '#date_flexible' => 0,
      '#date_format' => \Drupal::config('core.date_formats.short')->get('pattern', 'm/d/Y - H:i'),
      '#date_text_parts' => array(),
      '#date_increment' => 1,
      '#date_year_range' => '-3:+3',
      '#date_label_position' => 'above',
      '#process' => array($class, 'process'),
      '#theme_wrappers' => array('date_text'),
      '#value_callback' => array($class, 'valueCallback')

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
    $return = '';
    if ($input !== FALSE) {
      $return = $input;
    }
    elseif (!empty($element['#default_value'])) {
      $return = array('timezone' => $element['#default_value']);
    }
    return $return;
  }

  /**
   * Process an individual date element.
   */
  protected function process(&$element, &$form_state, $form) {
    if (date_hidden_element($element)) {
      return $element;
    }

    $element['#tree'] = TRUE;
    $label = theme('date_part_label_timezone', array('part_type' => 'select', 'element' => $element));
    $element['timezone'] = array(
      '#type' => 'select',
      '#title' => $label,
      '#title_display' => $element['#date_label_position'] == 'above' ? 'before' : 'invisible',
      '#options' => date_timezone_names($element['#required']),
      '#value' => $element['#value'],
      '#weight' => $element['#weight'],
      '#required' => $element['#required'],
      '#theme' => 'date_select_element',
      '#theme_wrappers' => array('form_element'),
    );
    if (isset($element['#element_validate'])) {
      $element['#element_validate'][] = array(get_class($this), 'validate');
    }
    else {
      $element['#element_validate'] = array(get_class($this), 'validate');
    }

    $context = array(
      'form' => $form,
    );
    drupal_alter('date_timezone_process', $element, $form_state, $context);
  }

  /**
   *  Validation for text input.
   *
   * When used as a Views widget, the validation step always gets triggered,
   * even with no form submission. Before form submission $element['#value']
   * contains a string, after submission it contains an array.
   *
   */
  protected function validate(&$element, FormStateInterface $form_state, &$complete_form) {
    if (date_hidden_element($element)) {
      return;
    }
    $form_state->setValue($element, $element['#value']['timezone']);
  }

}
