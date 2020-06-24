<?php

namespace Drupal\course\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Entity\Course;

/**
 * Base class for Course object access plugins.
 */
abstract class CourseOutlinePluginBase extends PluginBase implements CourseOutlineInterface {

  /**
   * Render a course outline.
   *
   * @param \Drupal\course\Plugin\Course $course
   * @param \Drupal\course\Plugin\AccountInterface $account
   *
   * @return array
   *   Render array.
   */
  abstract function render(Course $course, AccountInterface $account);
}
