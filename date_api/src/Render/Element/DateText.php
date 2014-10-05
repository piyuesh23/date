<?php

/**
 * @file
 * Contains \Drupal\date_api\Render\Element\DateText.
 */

namespace Drupal\date_api\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\date_api\DateObject;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a one-line text field form element.
 *
 * @FormElement("date_text")
 */
class DateText extends DateApiElementBase {

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
    $return = array('year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => '');
    $date = NULL;
    if ($input !== FALSE) {
      $return = $input;
      $date = self::selectInputDate($element, $input);
    }
    elseif (!empty($element['#default_value'])) {
      $date = self::getDefaultDate($element);
    }
    $granularity = date_format_order($element['#date_format']);
    $formats = array('year' => 'Y', 'month' => 'n', 'day' => 'j', 'hour' => 'H', 'minute' => 'i', 'second' => 's');
    foreach ($granularity as $field) {
      if ($field != 'timezone') {
        $return[$field] = date_is_date($date) ? $date->format($formats[$field]) : '';
      }
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
    $element['#theme_wrappers'] = array('date_text');
    $element['date']['#value'] = $element['#value']['date'];
    $element['date']['#type'] = 'textfield';
    $element['date']['#weight'] = !empty($element['date']['#weight']) ? $element['date']['#weight'] : $element['#weight'];
    $element['date']['#attributes'] = array('class' => isset($element['#attributes']['class']) ? $element['#attributes']['class'] += array('date-date') : array('date-date'));
    $now = date_example_date();
    $element['date']['#title'] = t('Date');
    $element['date']['#title_display'] = 'invisible';
    $element['date']['#description'] = ' ' . t('Format: @date', array('@date' => date_format_date(date_example_date(), 'custom', $element['#date_format'])));
    $element['date']['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;

    // Keep the system from creating an error message for the sub-element.
    // We'll set our own message on the parent element.
    // $element['date']['#required'] = $element['#required'];
    $element['date']['#theme'] = 'date_textfield_element';
    if (isset($element['#element_validate'])) {
      array_push($element['#element_validate'], 'date_text_validate');
    }
    else {
      $element['#element_validate'] = array('date_text_validate');
    }
    if (!empty($element['#force_value'])) {
      $element['date']['#value'] = $element['date']['#default_value'];
    }

    $context = array(
      'form' => $form,
    );
    drupal_alter('date_text_process', $element, $form_state, $context);
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
    $granularity = date_format_order($element['#date_format']);
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
    foreach (date_nongranularity($granularity) as $part) {
      unset($input[$part]);
    }

    $date = new DateObject($input, $element['#date_timezone']);
    if (is_object($date)) {
      $date->limitGranularity($granularity);
      if ($date->validGranularity($granularity, $element['#date_flexible'])) {
        date_increment_round($date, $element['#date_increment']);
      }
      return $date;
    }
    return NULL;
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

    if (is_string($element['#value'])) {
      return;
    }
    $input_exists = NULL;
    $input = NestedArray::setValue($form_state['values'], $element['#parents'], $input_exists);

    drupal_alter('date_text_pre_validate', $element, $form_state, $input);

    $label = !empty($element['#date_title']) ? $element['#date_title'] : (!empty($element['#title']) ? $element['#title'] : '');
    $date = date_text_input_date($element, $input);

    // If the field has errors, display them.
    // If something was input but there is no date, the date is invalid.
    // If the field is empty and required, set error message and return.
    $error_field = implode('][', $element['#parents']);
    if (empty($date) || !empty($date->errors)) {
      if (is_object($date) && !empty($date->errors)) {
        $message = t('The value input for field %field is invalid:', array('%field' => $label));
        $message .= '<br />' . implode('<br />', $date->errors);
        $form_state->setError($error_field, $message);
        return;
      }
      if (!empty($element['#required'])) {
        $message = t('A valid date is required for %title.', array('%title' => $label));
        $form_state->setError($error_field, $message);
        return;
      }
      // Fall through, some other error.
      if (!empty($input['date'])) {
        $form_state->setError($element, t('%title is invalid.', array('%title' => $label)));
        return;
      }
    }
    $value = !empty($date) ? $date->format(DATE_FORMAT_DATETIME) : NULL;
    $form_state->setValue($element, $value);

  }

}
