<?php

namespace Drupal\course\Plugin\Block;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use function course_get_context;
use function drupal_get_path;

/**
 * Provides a course navigation block.
 *
 * @Block(
 *   id = "course_navigation_block",
 *   admin_label = @Translation("Course navigation"),
 *   category = @Translation("Course"),
 * )
 */
class CourseNavigationBlock extends BlockBase {

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
    $build['#cache']['max-age'] = 0;
    $course = course_get_context();
    $account = \Drupal::currentUser();

    if ($course && $course->isEnrolled($account)) {
      $links = $course->getNavigation($account);

      $items = array();
      foreach ($links as $key => $value) {
        $items[] = array(
          '#class' => array('course-nav-' . $key),
          '#markup' => $value->toString(),
        );
      }

      // Add javascript poller to update the next step button.
      $build['nav']['#attached']['library'][] = 'course/nav';

      $build['nav'] = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#title' => '',
        '#type' => 'ul',
        '#attributes' => array('id' => 'course-nav'),
      ];
    }

    return $build;
  }

}
