<?php

namespace Drupal\course_test\Plugin\course\CourseObject;

/**
 * @CourseObject(
 *   id = "course_test_graded_object",
 *   label = "Test graded course object",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_test\Plugin\course\CourseObject\CourseObjectTestFulfillment"
 *   }
 * )
 */
class CourseObjectGradedTest extends CourseObjectTest {

  public function isGraded() {
    return TRUE;
  }

}
