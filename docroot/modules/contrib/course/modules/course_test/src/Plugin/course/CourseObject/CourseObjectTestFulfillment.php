<?php

namespace Drupal\course_test\Plugin\course\CourseObject;

use Drupal\course\Entity\CourseObjectFulfillment;

/**
 * Course fulfillment class for test.
 */
class CourseObjectTestFulfillment extends CourseObjectFulfillment {

  /**
   * Define storage for fulfillment values.
   */
  function optionsDefinition() {
    return array('test_value' => NULL);
  }

}
