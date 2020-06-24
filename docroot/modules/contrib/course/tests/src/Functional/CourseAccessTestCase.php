<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for conditional event-based access to course objects.
 *
 * @group course
 */
class CourseAccessTestCase extends CourseTestCase {

  /**
   * Test the enrollment duration. This does not test the enrollment end date
   * being set correctly.
   *
   * @see CourseEnrollmentTestCase::testCourseDuration()
   */
  function testDurationExpiration() {
    $course = $this->createCourse();
    // Set duration to 30 days.
    $course->set('duration', 86400 * 30);
    $course->save();
    $course->enroll($this->student_user);

    $this->assertTrue($course->access('take', $this->student_user));

    // Expire the duration.
    $enroll = $course->getEnrollment($this->student_user);
    $enroll->set('enroll_end', 1);
    $enroll->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course')->resetCache();
    $this->assertFalse($course->access('take', $this->student_user), 'User cannot access course with expired enrollment.');
  }

  /**
   * Test the open/close date functionality.
   */
  function testReleaseExpiration() {
    $course = $this->createCourse();

    $now = \Drupal::time()->getRequestTime();
    $formatter = \Drupal::service('date.formatter');

    // Make sure user can get in with no open/close set.
    $this->assertTrue($course->access('enroll', $this->student_user), 'User can enroll in course past start date.');

    // Test a course that is not yet open.
    $course->set('course_date', ['value' => $formatter->format($now + 30, 'custom', DATETIME_DATETIME_STORAGE_FORMAT)]);
    $course->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course')->resetCache();
    $this->assertFalse($course->access('enroll', $this->student_user), 'User cannot enroll in not yet open course.');

    // Test an opened course that is closed.
    $course->set('course_date', ['value' => $formatter->format($now - 60, 'custom', DATETIME_DATETIME_STORAGE_FORMAT), 'end_value' => $formatter->format($now - 30, 'custom', DATETIME_DATETIME_STORAGE_FORMAT)]);
    $course->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course')->resetCache();
    $this->assertFalse($course->access('enroll', $this->student_user), 'User cannot enroll in expired course.');

    // Enroll the user. User should still not be able to take course if it is
    // expired.
    $course->enroll($this->student_user);
    \Drupal::entityTypeManager()->getAccessControlHandler('course')->resetCache();
    $this->assertFalse($course->access('enroll', $this->student_user), 'User cannot take expired course.');
  }

}
