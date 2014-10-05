<?php

/**
 * @file
 * Contains \Drupal\date_tools\Controller\PageController.
 */

namespace Drupal\date_tools\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for date_tools module routes.
 */
class PageController extends ControllerBase {


  /**
   * @return mixed|string
   */
  public function getAdminPage() {
    return $this->t('Dates and calendars can be complicated to set up. The <a href="!date_wizard">Date wizard</a> makes it easy to create a simple date content type and related calendar. ', array('!date_wizard' => 'admin/config/date/tools/date_wizard'));
  }

}
