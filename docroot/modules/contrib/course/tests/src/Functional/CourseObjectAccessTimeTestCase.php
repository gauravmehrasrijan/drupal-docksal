<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for course object access based on time.
 *
 * @group course
 */
class CourseObjectAccessTimeTestCase extends CourseTestCase {

  /**
   * Test course object open date.
   */
  public function testCourseObjectRestrictByRelease() {
    $now = \Drupal::time()->getRequestTime();
    $course = $this->createCourse();
    $options = array();

    // Test restricting by release.
    $o1 = $this->createCourseObject($course);

    // Set the release to 100 seconds in the future.
    $options['plugins']['access']['timing']['release'] = gmdate('c', $now + 100);
    $o1->addOptions($options)->save();
    $this->assertFalse($o1->access('take', $this->student_user), 'Check that user cannot take not released object.');
    $this->assertTrue($o1->access('see', $this->student_user), 'Check that user can still see not released object.');

    // Set the hide until release option.
    $options['plugins']['access']['timing']['release_hidden'] = 1;
    $o1->addOptions($options)->save();
    $this->assertFalse($o1->access('see', $this->student_user), 'Check that object is hidden if hidden is checked and object is not released.');
  }

  /**
   * Test course object expiration date.
   */
  public function testCourseObjectRestrictByExpiration() {
    $now = \Drupal::time()->getRequestTime();
    $course = $this->createCourse();
    $options = array();

    // Test restricting by expiration.
    $o2 = $this->createCourseObject($course);

    // Set the expiration to 10 seconds ago.
    $options['plugins']['access']['timing']['expiration'] = gmdate('c', $now - 100);
    $o2->addOptions($options)->save();
    $this->assertFalse($o2->access('take', $this->student_user), 'Check that user cannot take expired course object.');
    $this->assertTrue($o2->access('see', $this->student_user), 'Check that user can see expired course object.');

    // Set the hide when expired option.
    $options['plugins']['access']['timing']['expiration_hidden'] = 1;
    $o2->addOptions($options)->save();
    $this->assertFalse($o2->access('see', $this->student_user), 'Check that object is hidden if hidden is checked and object is expired.');
  }

  /**
   * Check that user cannot access course object outside of duration period.
   */
  public function testCourseObjectDuration() {
    $now = \Drupal::time()->getRequestTime();
    $course = $this->createCourse();
    $options = array();

    $course->enroll($this->student_user);
    $o3 = $this->createCourseObject($course);

    // Start the course object 5 minutes ago.
    $o3->getFulfillment($this->student_user)->setOption('date_started', $now - 300)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertTrue($o3->access('take', $this->student_user), 'Check that user can access course object without duration.');

    // Set the duration to 1 minute.
    $options['plugins']['access']['timing']['duration'] = 60;
    $o3->addOptions($options)->save();
    $this->assertFalse($o3->access('take', $this->student_user), 'Check that user cannot access course object when duration has passed.');

    // Extend the duration to 10 minutes.
    $options['plugins']['access']['timing']['duration'] = 600;
    $o3->addOptions($options)->save();
    $this->assertTrue($o3->access('take', $this->student_user), 'Check that user can access course object when duration has been extended.');
  }

}
