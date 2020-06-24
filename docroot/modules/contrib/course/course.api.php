<?php

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Entity\Course;

/**
 * @file
 * Hooks provided by Course module.
 *
 * These entity types provided by Course also have entity API hooks.
 *
 * course_report
 * course_object
 * course_object_fulfillment
 * course_enrollment
 *
 * So for example
 *
 * hook_course_report_presave(&$course_report)
 * hook_course_object_fulfillment_insert($course_object_fulfillment)
 *
 * Enjoy :)
 */
/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to add links to the course completion landing page, such as
 * post-course actions.
 *
 * @param array $links
 *   By reference. Currently an array of three elements:
 *   - 0: $path param for l().
 *   - 1: $text param for l().
 *   - 2: A description, suitable for theme_form_element().
 * @param object $course_node
 *   The course node object.
 * @param object $account
 *   The user who just took the course.
 *
 * @see course_completion_page()
 */
function hook_course_outline_completion_links_alter(&$links, $course_node, $account) {
  // Example: add a link.
  $links['gohome'] = array(t('Go home!'), '<front>', t('If you got this far, you
    deserve a link back home'));
}

/**
 * Allow modules to alter remaining incomplete links on the course completion
 * landing page.
 *
 * @param array $links
 *   Same as $links param for hook_course_outline_completion_links().
 * @param object $course_node
 *   The course node object.
 * @param object $account
 *   The user who just took the course.
 *
 * @see course_completion_page()
 */
function hook_course_outline_incomplete_links_alter(&$links, $course_node, $account) {
  // Example: change the default link.
  $links['course'] = array(t("Let's try that again"), "node/$course_node->nid/take", t('Looks like you missed something.'));
}

/**
 * Allow modules to restrict menu access to the take course tab.
 *
 * @param object $node
 *   The course node.
 * @param object $user
 *   The user to check access.
 *
 * @return boolean
 *   Any hook returning FALSE will restrict access to the take course tab.
 */
function hook_course_has_take($node, $user) {
  if ($node->type == 'imported_course') {
    // Users cannot take imported courses.
    return FALSE;
  }
}

/**
 * Allow modules to determine if this course should be restricted.
 *
 * If any module implementing this hook returns FALSE or an array containing
 * 'success' => FALSE, the course will be restricted.
 *
 * @param string $op
 *   Either 'enroll' or 'take'.
 * @param object $node
 *   The course node.
 * @param object $user
 *   The user who may or may not enroll/take the course.
 *
 * @return boolean|array
 *   Either FALSE, or an array containing:
 *   - success: Boolean. Indicates whether or not the user has permission to
 *     enroll or take this course.
 *   - message: String. If success is FALSE, a message to display to the user.
 */
function hook_course_access(Course $entity, $operation, AccountInterface $account) {
  if ($op == 'take') {
    // Example: do not allow users to take courses on Wednesdays.
    if (date('L') == 'wednesday') {
      $hooks['course_notopen'] = AccessResult::forbidden('Courses are closed on Wednesdays.');
    }
    // Example: however allow users to bypass enrollment restriction on Christmas.
    elseif ((date('m') == 12) && (date('d') == 25)) {
      $hooks['course_notopen'] = AccessResult::allowed();
    }

    return $hooks;
  }

  if ($op == 'enroll') {
    // Same usage as $op == 'take'.
  }
}

/**
 * Implements hook_course_access_alter().
 */
function hook_course_access_alter(&$hooks, $op, $node, $account) {
  if ($op == 'enroll') {
    $hooks['wait_a_minute'] = array(
      'message' => t('You cannot take this course.'),
      'weight' => 5,
      'success' => FALSE,
    );
  }
}
