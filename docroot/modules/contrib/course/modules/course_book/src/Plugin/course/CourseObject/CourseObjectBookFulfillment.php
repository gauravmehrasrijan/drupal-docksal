<?php

namespace Drupal\course_book\Plugin\course\CourseObject;

use Drupal\course\Entity\CourseObjectFulfillment;

/**
 * Course fulfillment class for books.
 */
class CourseObjectBookFulfillment extends CourseObjectFulfillment {

  /**
   * Define storage for book page views.
   */
  function optionsDefinition() {
    return array('book_fulfillment' => array());
  }

}
