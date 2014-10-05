<?php

/**
 * @file
 * Tests for Date Tools.
 */

namespace Drupal\date_tools\Tests;

use Drupal\simpletest\WebTestBase;

class DateToolsUiTest extends WebTestBase {

  /**
   * @var
   */
  protected $privileged_user;

  /**
   * @todo.
   */
  public static function getInfo() {
    return array(
      'name' => 'Date Tools',
      'description' => 'Test Date Wizard and other Date Tools.',
      'group' => 'Date',
    );
  }

  /**
   * @todo.
   */
  public function setUp() {
    // Load the date_api module.
    parent::setUp('field', 'field_ui', 'date_api', 'date', 'date_popup', 'date_tools');

    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(
      array('administer content types', 'administer nodes', 'bypass node access', 'administer date tools')
    );
    $this->drupalLogin($this->privileged_user);

    variable_set('date_format_long', 'D, m/d/Y - H:i');
  }

  /**
   * Creates a date field using the Date Wizard.
   */
  public function testTools() {
    $form_values = array('label' => 'Test', 'field_type' => 'datetime', 'widget_type' => 'date_select', 'todate' => '');
    $this->createDateWizard($form_values);
    $this->dateForm($options = 'select');
    $this->assertText('Thu, 10/07/2010 - 10:30', 'Found the correct date for the Date Wizard datetime field using the date_select widget.');
    $this->deleteDateField();
  }

  /**
   * Tests that date field functions properly.
   */
  function dateForm($options) {
    $edit = array();
    $edit['title'] = $this->randomString(8);
    $edit['body[und][0][value]'] = $this->randomString(16);
    if ($options == 'select') {
      $edit['field_test[und][0][value][year]'] = '2010';
      $edit['field_test[und][0][value][month]'] = '10';
      $edit['field_test[und][0][value][day]'] = '7';
      $edit['field_test[und][0][value][hour]'] = '10';
      $edit['field_test[und][0][value][minute]'] = '30';
    }
    elseif ($options == 'text') {
      $edit['field_test[und][0][value][date]'] = '10/07/2010 - 10:30';
    }
    elseif ($options == 'popup') {
      $edit['field_test[und][0][value][date]'] = '10/07/2010';
      $edit['field_test[und][0][value][time]'] = '10:30';
    }
    $this->drupalPost('node/add/story', $edit, t('Save'));
    $this->assertText($edit['body[und][0][value]'], 'Test node has been created');
  }

  /**
   * @todo.
   */
  function deleteDateField() {
    $this->drupalGet('admin/structure/types/manage/story/fields');
    $this->clickLink('delete');
    $this->drupalPost(NULL, NULL, t('Delete'));
    $this->assertText('The field Test has been deleted from the Story content type.', 'Removed date field.');
  }

  /**
   * Creates a date field using the Date Wizard.
   */
  function createDateWizard($edit) {
    $edit += array(
      'bundle' => 'story',
      'name' => 'Story',
      'type_description' => 'A test content type.',
      'field_name' => 'test',
      'label' => 'Test',
      'widget_type' => 'date_select',
      'todate' => 'optional',
      'field_type' => 'date',
      'granularity[]' => array('year', 'month', 'day', 'hour', 'minute'),
      'tz_handling' => 'site',
      'year_range' => '2010:+2',
    );
    $this->drupalPost('admin/config/date/tools/date_wizard', $edit, t('Save'));
    $this->assertText('Your date field Test has been created.', 'Create a date field using the Date Wizard.');
  }

}
