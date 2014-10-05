<?php

/**
 * @file
 * Contains \Drupal\date\Render\Element\DateCombo.
 */

namespace Drupal\date\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a one-line text field form element.
 *
 * @FormElement("date_combo")
 */
class DateCombo extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $type = array(
      '#input' => TRUE,
      '#delta' => 0,
      '#columns' => array('value', 'value2', 'timezone', 'offset', 'offset2'),
      '#process' => array($class, 'process'),
      '#element_validate' => array($class, 'valueCallback'),
      '#theme_wrappers' => array('date_combo'),
    );

    // @TODO: Check what ctools come up with.
    // if (module_exists('ctools')) {
    //  $type['date_combo']['#pre_render'] = array('ctools_dependent_pre_render');
    // }
    return $type;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {

    // Disabled and hidden elements won't have any input and don't need validation,
    // we just need to re-save the original values, from before they were processed into
    // widget arrays and timezone-adjusted.
    if (date_hidden_element($element) || !empty($element['#disabled'])) {
      form_set_value($element, $element['#date_items'], $form_state);
      return;
    }

    $field_name = $element['#field_name'];
    $delta = $element['#delta'];
    $langcode = $element['#language'];

    $form_values = NestedArray::setValue($form_state['values'], $element['#field_parents']);

    // If the whole field is empty and that's OK, stop now.
    if (empty($input[$field_name]) && !$element['#required']) {
      return;
    }

    $item = NestedArray::setValue($form_state['values'], $element['#parents']);
    $posted = NestedArray::setValue($form_state['input'], $element['#parents']);

    $field = field_widget_field($element, $form_state);
    $instance = field_widget_instance($element, $form_state);

    $context = array(
      'field' => $field,
      'instance' => $instance,
      'item' => $item,
    );

    \Drupal::moduleHandler()->alter('date_combo_pre_validate', $element, $form_state, $context);

    $from_field = 'value';
    $to_field = 'value2';
    $tz_field = 'timezone';
    $offset_field = 'offset';
    $offset_field2 = 'offset2';

    // Check for empty 'Start date', which could either be an empty
    // value or an array of empty values, depending on the widget.
    $empty = TRUE;
    if (!empty($item[$from_field])) {
      if (!is_array($item[$from_field])) {
        $empty = FALSE;
      }
      else {
        foreach ($item[$from_field] as $key => $value) {
          if (!empty($value)) {
            $empty = FALSE;
            break;
          }
        }
      }
    }

    // An 'End' date without a 'Start' date is a validation error.
    if ($empty && !empty($item[$to_field])) {
      if (!is_array($item[$to_field])) {
        $form_state->setError($element, t("A 'Start date' date is required if an 'end date' is supplied for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])));
        $empty = FALSE;
      }
      else {
        foreach ($item[$to_field] as $key => $value) {
          if (!empty($value)) {
            $form_state->setError($element, t("A 'Start date' date is required if an 'End date' is supplied for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])));
            $empty = FALSE;
            break;
          }
        }
      }
    }

    // If the user chose the option to not show the end date, just swap in the
    // start date as that value so the start and end dates are the same.
    if ($field['settings']['todate'] == 'optional' && empty($item['show_todate'])) {
      $item[$to_field] = $item[$from_field];
      $posted[$to_field] = $posted[$from_field];
    }

    if ($empty) {
      $item = date_element_empty($element, $form_state);
      if (!$element['#required']) {
        return;
      }
    }
    // Don't look for further errors if errors are already flagged
    // because otherwise we'll show errors on the nested elements
    // more than once.
    elseif (!$form_state->hasAnyErrors()) {

      $timezone = !empty($item[$tz_field]) ? $item[$tz_field] : $element['#date_timezone'];
      $timezone_db = date_get_timezone_db($field['settings']['tz_handling']);
      $element[$from_field]['#date_timezone'] = $timezone;
      $from_date = date_input_date($field, $instance, $element[$from_field], $posted[$from_field]);

      if (!empty($field['settings']['todate'])) {
        $element[$to_field]['#date_timezone'] = $timezone;
        $to_date = date_input_date($field, $instance, $element[$to_field], $posted[$to_field]);
      }
      else {
        $to_date = $from_date;
      }

      // Neither the start date nor the end date should be empty at this point
      // unless they held values that couldn't be evaluated.

      if (!$instance['required'] && (!date_is_date($from_date) || !date_is_date($to_date))) {
        $item = date_element_empty($element, $form_state);
        $errors[] = t('The dates are invalid.');
      }
      elseif (!empty($field['settings']['todate']) && $from_date > $to_date) {
        $form_state->setValue($element[$to_field], $to_date, $form_state);
        $errors[] = t('The End date must be greater than the Start date.');
      }
      else {
        // Convert input dates back to their UTC values and re-format to ISO
        // or UNIX instead of the DATETIME format used in element processing.
        $item[$tz_field] = $timezone;

        // Update the context for changes in the $item, and allow other modules to
        // alter the computed local dates.
        $context['item'] = $item;
        // We can only pass two additional values to \Drupal::moduleHandler()->alter(), so $element
        // needs to be included in $context.
        $context['element'] = $element;
        \Drupal::moduleHandler()->alter('date_combo_validate_date_start', $from_date, $form_state, $context);
        \Drupal::moduleHandler()->alter('date_combo_validate_date_end', $to_date, $form_state, $context);

        $item[$offset_field] = date_offset_get($from_date);

        $test_from = date_format($from_date, 'r');
        $test_to = date_format($to_date, 'r');

        $item[$offset_field2] = date_offset_get($to_date);
        date_timezone_set($from_date, timezone_open($timezone_db));
        date_timezone_set($to_date, timezone_open($timezone_db));
        $item[$from_field] = date_format($from_date, date_type_format($field['type']));
        $item[$to_field] = date_format($to_date, date_type_format($field['type']));
        if (isset($form_values[$field_name]['rrule'])) {
          $item['rrule'] = $form_values[$field['field_name']]['rrule'];
        }

        // If the db timezone is not the same as the display timezone
        // and we are using a date with time granularity,
        // test a roundtrip back to the original timezone to catch
        // invalid dates, like 2AM on the day that spring daylight savings
        // time begins in the US.
        $granularity = date_format_order($element[$from_field]['#date_format']);
        if ($timezone != $timezone_db && date_has_time($granularity)) {
          date_timezone_set($from_date, timezone_open($timezone));
          date_timezone_set($to_date, timezone_open($timezone));

          if ($test_from != date_format($from_date, 'r')) {
            $errors[] = t('The Start date is invalid.');
          }
          if ($test_to != date_format($to_date, 'r')) {
            $errors[] = t('The End date is invalid.');
          }
        }
        if (empty($errors)) {
          form_set_value($element, $item, $form_state);
        }
      }
    }
    if (!empty($errors)) {
      if ($field['cardinality']) {
        $form_state->setError($element, t('There are errors in @field_name value #@delta:', array('@field_name' => $instance['label'], '@delta' => $delta + 1)) . theme('item_list', array('items' => $errors)));
      }
      else {
        $form_state->setError($element, t('There are errors in @field_name:', array('@field_name' => $instance['label'])) . theme('item_list', array('items' => $errors)));
      }
    }
  }

  /**
   * Process an individual date element.
   */
  protected function process($element, &$form_state, $form) {

    if (date_hidden_element($element)) {
      // A hidden value for a new entity that had its end date set to blank
      // will not get processed later to populate the end date, so set it here.
      if (isset($element['#value']['value2']) && empty($element['#value']['value2'])) {
        $element['#value']['value2'] = $element['#value']['value'];
      }
      return $element;
    }

    $field_name = $element['#field_name'];
    $delta = $element['#delta'];
    $bundle = $element['#bundle'];
    $entity_type = $element['#entity_type'];
    $langcode = $element['#language'];
    $date_is_default = $element['#date_is_default'];

    $field = field_widget_field($element, $form_state);
    $instance = field_widget_instance($element, $form_state);

    // Figure out how many items are in the form, including new ones added by ajax.
    $field_state = field_form_get_state($element['#field_parents'], $field_name, $element['#language'], $form_state);
    $items_count = $field_state['items_count'];

    $columns = $element['#columns'];
    if (isset($columns['rrule'])) {
      unset($columns['rrule']);
    }
    $from_field = 'value';
    $to_field = 'value2';
    $tz_field = 'timezone';
    $offset_field = 'offset';
    $offset_field2 = 'offset2';

    // Convert UTC dates to their local values in DATETIME format,
    // and adjust the default values as specified in the field settings.

    // It would seem to make sense to do this conversion when the data
    // is loaded instead of when the form is created, but the loaded
    // field data is cached and we can't cache dates that have been converted
    // to the timezone of an individual user, so we cache the UTC values
    // instead and do our conversion to local dates in the form and
    // in the formatters.
    $process = date_process_values($field, $instance);
    foreach ($process as $processed) {
      if (!isset($element['#default_value'][$processed])) {
        $element['#default_value'][$processed] = '';
      }
      $date = date_local_date($element['#default_value'], $element['#date_timezone'], $field, $instance, $processed);
      $element['#default_value'][$processed] = is_object($date) ? date_format($date, DATE_FORMAT_DATETIME) : '';
    }

    // Blank out the end date for optional end dates that match the start date,
    // except when this is a new node that has default values that should be honored.
    if (!$date_is_default && $field['settings']['todate'] != 'required'
      && !empty($element['#default_value'][$to_field])
      && $element['#default_value'][$to_field] == $element['#default_value'][$from_field]) {
      unset($element['#default_value'][$to_field]);
    }

    $show_todate = !empty($form_state['values']['show_todate']) || !empty($element['#default_value'][$to_field]) || $field['settings']['todate'] == 'required';
    $element['show_todate'] = array(
      '#title' => t('Show End Date'),
      '#type' => 'checkbox',
      '#default_value' => $show_todate,
      '#weight' => -20,
      '#access' => $field['settings']['todate'] == 'optional',
      '#prefix' => '<div class="date-float">',
      '#suffix' => '</div>',
    );

    $parents = $element['#parents'];
    $first_parent = array_shift($parents);
    $show_id = $first_parent . '[' . implode('][', $parents) . '][show_todate]';

    $element[$from_field] = array(
      '#field'         => $field,
      '#instance'      => $instance,
      '#weight'        => $instance['widget']['weight'],
      '#required'      => ($element['#required'] && $delta == 0) ? 1 : 0,
      '#default_value' => isset($element['#default_value'][$from_field]) ? $element['#default_value'][$from_field] : '',
      '#delta'         => $delta,
      '#date_timezone' => $element['#date_timezone'],
      '#date_format'      => date_limit_format(date_input_format($element, $field, $instance), $field['settings']['granularity']),
      '#date_text_parts'  => (array) $instance['widget']['settings']['text_parts'],
      '#date_increment'   => $instance['widget']['settings']['increment'],
      '#date_year_range'  => $instance['widget']['settings']['year_range'],
      '#date_label_position' => $instance['widget']['settings']['label_position'],
    );

    $description =  !empty($element['#description']) ? t($element['#description']) : '';
    unset($element['#description']);

    // Give this element the right type, using a Date API
    // or a Date Popup element type.
    $element[$from_field]['#attributes'] = array('class' => array('date-clear'));
    $element[$from_field]['#wrapper_attributes'] = array('class' => array());
    $element[$from_field]['#wrapper_attributes']['class'][] = 'date-no-float';

    switch ($instance['widget']['type']) {
      case 'date_select':
        $element[$from_field]['#type'] = 'date_select';
        $element[$from_field]['#theme_wrappers'] = array('date_select');
        $element['#attached']['js'][] = drupal_get_path('module', 'date') . '/date.js';
        $element[$from_field]['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;
        break;
      case 'date_popup':
        $element[$from_field]['#type'] = 'date_popup';
        $element[$from_field]['#theme_wrappers'] = array('date_popup');
        $element[$from_field]['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;
        break;
      default:
        $element[$from_field]['#type'] = 'date_text';
        $element[$from_field]['#theme_wrappers'] = array('date_text');
        $element[$from_field]['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;
        break;
    }

    // If this field uses the 'End', add matching element
    // for the 'End' date, and adapt titles to make it clear which
    // is the 'Start' and which is the 'End' .

    if (!empty($field['settings']['todate'])) {
      $element[$to_field] = $element[$from_field];
      $element[$from_field]['#title_display'] = 'none';
      $element[$to_field]['#title'] = t('to:');
      $element[$from_field]['#wrapper_attributes']['class'][] = 'start-date-wrapper';
      $element[$to_field]['#wrapper_attributes']['class'][] = 'end-date-wrapper';
      $element[$to_field]['#default_value'] = isset($element['#default_value'][$to_field]) ? $element['#default_value'][$to_field] : '';
      $element[$to_field]['#required'] = ($element[$from_field]['#required'] && $field['settings']['todate'] == 'required');
      $element[$to_field]['#weight'] += .2;
      $element[$to_field]['#prefix'] = '';
      // Users with JS enabled will never see initially blank values for the end
      // date (see Drupal.date.EndDateHandler()), so hide the message for them.
      $description .= '<span class="js-hide"> ' . t("Empty 'End date' values will use the 'Start date' values.") . '</span>';
      $element['#fieldset_description'] = $description;
      if ($field['settings']['todate'] == 'optional') {
        $element[$to_field]['#states'] = array(
          'visible' => array(
            'input[name="' . $show_id . '"]' => array('checked' => TRUE),
          ));
      }
    }
    else {
      $element[$from_field]['#description'] = $description;
    }

    // Create label for error messages that make sense in multiple values
    // and when the title field is left blank.
    if ($field['cardinality'] <> 1 && empty($field['settings']['repeat'])) {
      $element[$from_field]['#date_title'] = t('@field_name Start date value #@delta', array('@field_name' => $instance['label'], '@delta' => $delta + 1));
      if (!empty($field['settings']['todate'])) {
        $element[$to_field]['#date_title'] = t('@field_name End date value #@delta', array('@field_name' => $instance['label'], '@delta' => $delta + 1));
      }
    }
    elseif (!empty($field['settings']['todate'])) {
      $element[$from_field]['#date_title'] = t('@field_name Start date', array('@field_name' => $instance['label']));
      $element[$to_field]['#date_title'] = t('@field_name End date', array('@field_name' => $instance['label']));
    }
    else {
      $element[$from_field]['#date_title'] = t('@field_name', array('@field_name' => $instance['label']));
    }

    $context = array(
      'field' => $field,
      'instance' => $instance,
      'form' => $form,
    );
    \Drupal::moduleHandler()->alter('date_combo_process', $element, $form_state, $context);

    return $element;
  }
  
}
