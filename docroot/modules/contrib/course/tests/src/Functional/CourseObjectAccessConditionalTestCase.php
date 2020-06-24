<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for conditional event-based access to course objects.
 *
 * @group course
 */
class CourseObjectAccessConditionalTestCase extends CourseTestCase {

  /**
   * Test the time based trigger for object access.
   */
  function testTimeAfterStart() {
    $course = $this->createCourse();
    $this->createCourseObject($course);
    $this->createCourseObject($course);

    // Set up a course object that should appear 5 minutes after the first
    // object is started.
    $co = array_values($course->getObjects());
    $co1 = $co[0];
    $co2 = $co[1];

    $set = array();
    $set['plugins']['access']['conditional'] = array(
      'conditional_type' => 'started',
      'conditional_time' => 300,
      'conditional_object' => $co1->id(),
      'conditional_hidden' => 0,
    );
    $hidden['plugins']['access']['conditional']['conditional_hidden'] = 1;
    $co2->addOptions($set)->save();

    $course->enroll($this->student_user);
    $this->assertTrue($co1->access('take', $this->student_user), 'Check that user can access first (depended on) object.');
    // Mark first object as complete.
    $co1->getFulfillment($this->student_user)->setComplete(1)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertFalse($co2->access('take', $this->student_user), 'Check that user still cannot access second (dependent) object, even though first is complete.');

    // Check visibility.
    $this->assertTrue($co2->access('see', $this->student_user), 'Check that user can still see pending course object.');
    $co2->addOptions($hidden)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertFalse($co2->access('see', $this->student_user), 'Check that user cannot still see pending course object when hidden is checked.');

    // Set the completion of this object to more than 5 minutes ago.
    $co1->getFulfillment($this->student_user)->setOption('date_started', \Drupal::time()->getRequestTime() - 301)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertTrue($co2->access('take', $this->student_user), 'Check that user can access second course object after time has elapsed.');
  }

  /**
   * Test the completion based trigger for object access.
   */
  function testTimeAfterCompletion() {
    $course = $this->createCourse();
    $this->createCourseObject($course);
    $this->createCourseObject($course);

    // Set up a course object that should appear 5 minutes after the first
    // object is completed.
    $co = array_values($course->getObjects());
    $co1 = $co[0];
    $co2 = $co[1];

    $set = array();
    $set['plugins']['access']['conditional'] = array(
      'conditional_type' => 'completed',
      'conditional_time' => 300,
      'conditional_object' => $co1->getId(),
      'conditional_hidden' => 0,
    );
    $hidden['plugins']['access']['conditional']['conditional_hidden'] = 1;
    $co2->addOptions($set)->save();

    $course->enroll($this->student_user);
    $this->assertTrue($co1->access('take', $this->student_user), 'Check that user can access first (depended on) object.');
    // Mark first object as complete.
    $co1->getFulfillment($this->student_user)->setComplete(1)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertFalse($co2->access('take', $this->student_user), 'Check that user still cannot access second (dependent) object, even though first is complete.');

    // Check visibility.
    $this->assertTrue($co2->access('see', $this->student_user), 'Check that user can still see pending course object.');
    $co2->addOptions($hidden)->save();
    $this->assertFalse($co2->access('see', $this->student_user), 'Check that user cannot still see pending course object when hidden is checked.');

    // Set the completion of this object to more than 5 minutes ago.
    $co1->getFulfillment($this->student_user)->setOption('date_completed', \Drupal::time()->getRequestTime() - 301)->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
    $this->assertTrue($co2->access('take', $this->student_user), 'Check that user can access second course object after time has elapsed.');
  }

}
