<?php

namespace Drupal\course\Plugin\Block;

use Drupal;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Plugin\CourseOutlinePluginBase;
use function course_get_context;

/**
 * Provides a course outline block.
 *
 * @Block(
 *   id = "course_outline_block",
 *   admin_label = @Translation("Course outline"),
 *   category = @Translation("Course"),
 * )
 */
class CourseOutlineBlock extends BlockBase {

  public function getCacheMaxAge() {
    return 0;
  }

  protected function blockAccess(AccountInterface $account) {
    return course_get_context() ? AccessResultAllowed::allowed() : AccessResultForbidden::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#cache']['max-age'] = 0;
    $course = course_get_context();
    $account = Drupal::currentUser();

    if ($course && $course->isEnrolled($account)) {
      // Display the configured outline handler output.
      /* @var $outlinePlugin CourseOutlinePluginBase */
      $outlinePlugin = Drupal::service('plugin.manager.course.outline')->createInstance($course->get('outline')->getString());
      $outline = $outlinePlugin->render($course, $account);
      $build['outline'] = $outline;
    }

    return $build;
  }

}
