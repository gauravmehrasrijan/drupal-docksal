<?php

namespace Drupal\course\Plugin\course\CourseOutline;

use Drupal\Core\Session\AccountInterface;
use Drupal\course\Entity\Course;
use Drupal\course\Plugin\CourseOutlinePluginBase;

/**
 * @CourseOutline(
 *   id = "none",
 *   label = @Translation("None"),
 * )
 */
class CourseOutlineNone extends CourseOutlinePluginBase {

  public function render(Course $course, AccountInterface $account): array {

  }

}
