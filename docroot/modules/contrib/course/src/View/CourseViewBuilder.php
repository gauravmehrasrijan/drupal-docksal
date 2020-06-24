<?php

namespace Drupal\course\View;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Link;
use Drupal\course\Entity\Course;

class CourseViewBuilder extends EntityViewBuilder {

  public function build(array $build) {
    $build = parent::build($build);

    /* @var $course Course */
    $course = $build['#course'];

    $account = \Drupal::currentUser();
    $enrollment = $course->getEnrollment($account);

    if ($enrollment && $enrollment->status) {
      // User is already in course. Check take access.
      $access = $course->access('take', $account, TRUE);
    }
    else {
      // User not in course. Check enroll access.
      $access = $course->access('enroll', $account, TRUE);
    }

    if (!$access->isAllowed()) {
      $build['course_messages']['#markup'] = '<div class="course-restriction">' . "<h2>" . 'Access denied' . "</h2>" . '<div class="course-restriction-message">' . $access->getReason() . '</div></div>';
    }
    else {
      // Render take course button.
      $build['course'] = [
        '#theme' => 'course_take_course_button',
        '#course' => $course,
      ];
    }

    return $build;
  }

}
