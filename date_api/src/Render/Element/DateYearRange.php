<?php

/**
 * @file
 * Contains \Drupal\date_api\Render\Element\DateYearRange.
 */

namespace Drupal\date_api\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a one-line text field form element.
 *
 * @FormElement("date_year_range")
 */
class DateYearRange extends FormElement {

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
      '#element_validate' => array($class, 'validateCallback'),
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
    // Convert the element's default value from a string to an array (to match
    // what we will get from the two textfields when the form is submitted).
    if ($input === FALSE) {
      list($years_back, $years_forward) = explode(':', $element['#default_value']);
      return array(
        'years_back' => $years_back,
        'years_forward' => $years_forward,
      );
    }
  }

  /**
   * Process an individual date element.
   */
  protected function process($element, &$form_state, $form) {

    // Year range is stored in the -3:+3 format, but collected as two separate
    // textfields.
    $element['years_back'] = array(
      '#type' => 'textfield',
      '#title' => t('Starting year'),
      '#default_value' => $element['#value']['years_back'],
      '#size' => 10,
      '#maxsize' => 10,
      '#attributes' => array('class' => array('select-list-with-custom-option', 'back')),
      '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
    );
    $element['years_forward'] = array(
      '#type' => 'textfield',
      '#title' => t('Ending year'),
      '#default_value' => $element['#value']['years_forward'],
      '#size' => 10,
      '#maxsize' => 10,
      '#attributes' => array('class' => array('select-list-with-custom-option', 'forward')),
      '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
    );

    $element['#tree'] = TRUE;
    $element['#attached']['js'][] = drupal_get_path('module', 'date_api') . '/date_year_range.js';

    $context = array(
      'form' => $form,
    );
    \Drupal::moduleHandler()->alter('date_year_range_process', $element, $form_state, $context);

    return $element;
  }

  protected function validate(&$element,FormStateInterface &$form_state, $form) {
    // Recombine the two submitted form values into the -3:+3 format we will
    // validate and save.
    $year_range_submitted = NestedArray::setValue($form_state['values'], $element['#parents']);
    $year_range = $year_range_submitted['years_back'] . ':' . $year_range_submitted['years_forward'];
    drupal_array_set_nested_value($form_state['values'], $element['#parents'], $year_range);
    if (!date_range_valid($year_range)) {
      $form_state->setError($element['years_back'], t('Starting year must be in the format -9, or an absolute year such as 1980.'));
      $form_state->setError($element['years_forward'], t('Ending year must be in the format +9, or an absolute year such as 2030.'));
    }
  }
  
}
