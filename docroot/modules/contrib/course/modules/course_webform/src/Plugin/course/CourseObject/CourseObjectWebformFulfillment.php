<?php

namespace Drupal\course_webform\Plugin\course\CourseObject;

use Drupal\course\Entity\CourseObjectFulfillment;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Course fulfillment class for webforms.
 */
class CourseObjectWebformFulfillment extends CourseObjectFulfillment {

  /**
   * Define storage for submission IDs.
   */
  function optionsDefinition() {
    return array('sids' => array());
  }

  /**
   * Remove all webform submissions associated with this fulfillment.
   */
  function delete() {
    if ($ids = $this->getOption('sids')) {
      foreach (WebformSubmission::loadMultiple($ids) as $submission) {
        $submission->delete();
      }
    }
    parent::delete();
  }

}
