<?php

namespace Drupal\course_test\Plugin\course\CourseObject;

use Drupal\course\Entity\CourseObject;

/**
 * @CourseObject(
 *   id = "course_test_object",
 *   label = "Test course object",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_test\Plugin\course\CourseObject\CourseObjectTestFulfillment"
 *   }
 * )
 */
class CourseObjectTest extends CourseObject {

  public static function getMaxOccurences() {
    return 3;
  }

  public function take() {
    return ['#markup' => t('I am a test course object with the title @title', array('@title' => $this->getOption('title')))];
  }

  public function optionsDefinition() {
    $options = parent::optionsDefinition();
    $options['test_option'] = NULL;
    return $options;
  }

}
