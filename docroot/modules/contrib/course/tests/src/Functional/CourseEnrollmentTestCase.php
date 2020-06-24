<?php

namespace Drupal\Tests\course\Functional;

use Drupal;
use Drupal\course\Entity\CourseEnrollment;
use Drupal\course\Entity\CourseEnrollmentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests for Course enrollment
 *
 * @group course
 */
class CourseEnrollmentTestCase extends CourseTestCase {

  /**
   * Test for enrollment access and timestamping.
   */
  function testCourseEnrollment() {
    $course = $this->createCourse();

    $result = $course->access('enroll', $this->student_user);
    $this->assertTrue($result, 'Check that the user is allowed to self enroll into this course.');

    $result = $course->access('take', $this->student_user);
    $this->assertFalse($result, 'Check that the user cannot enroll in the course.');

    $course->enroll($this->student_user);
    $result = $course->access('take', $this->student_user);
    $this->assertTrue($result, 'Check that the user can take the course.');
    $enroll = $course->getEnrollment($this->student_user);
    $this->assertTrue($enroll->id() > 0, 'Enrollment was created.');
    $this->assertTrue($enroll->get('created')->value > 0, 'Enrollment has a creation timestamp.');
    //$this->assertFalse($enroll->timestamp > 0, 'Check that user has not started course.');
    // Take the course
    $this->drupalLogin($this->student_user);
    $this->drupalGet("course/{$course->id()}/take");
    // The enrollment has changed.
    Drupal::entityTypeManager()->getStorage('course_enrollment')->resetCache();
    $enroll = $course->getEnrollment($this->student_user);
    $this->assertTrue($enroll->get('timestamp')->value > 0, 'Check for start of course timestamp.');
  }

  /**
   * Test a manual course enrollment. Ensure that created and started
   * timestamping works as expected.
   */
  function testCourseEnrollmentTimestamps() {
    $course = $this->createCourse();

    $course_enrollment = CourseEnrollment::create(array(
        'cid' => $course->id(),
        'uid' => $this->student_user->id(),
        'type' => $course->get('enrollment_type')->value,
    ));
    $course_enrollment->save();

    $initial_created = $course_enrollment->get('created')->value;
    $this->assertTrue($initial_created > 0, t('Enrollment creation date was set.'));
    $this->assertEqual($course_enrollment->get('timestamp')->value, 0, t('Enrollment timestamp not set.'));
    // Wait one second so we can confirm a time difference.
    sleep(1);

    $this->drupalLogin($this->student_user);
    $this->drupalGet("course/{$course->id()}/take");

    $new_enrollment = $course->getEnrollment($this->student_user);
    Drupal::entityTypeManager()->getStorage('course_enrollment')->resetCache();
    $this->assertEqual($initial_created, $new_enrollment->get('created')->value, t('Enrollment creation date retained.'));
    $this->assertTrue($new_enrollment->get('timestamp')->value > 0, t('Enrollment timestamp set.'));
  }

  /**
   * Test for course duration being set properly on enrollment.
   */
  function testCourseDuration() {
    $course = $this->createCourse(['duration' => 30]);
    $course->save();
    $enroll = $course->getEnrollment($this->student_user);
    $this->assertFalse($enroll, 'Check that enrollment does not exist.');
    $this->drupalLogin($this->student_user);
    $this->drupalGet("course/{$course->id()}/take");
    Drupal::entityTypeManager()->getStorage('course_enrollment')->resetCache();
    $enroll = $course->getEnrollment($this->student_user);
    $this->assertTrue($enroll->get('enroll_end')->value > \Drupal::time()->getRequestTime(), 'Duration end got set with course start.');
  }

  /**
   * Test course enrollment bundles.
   */
  function testCourseBundles() {

    $enrollment_type = CourseEnrollmentType::create(array(
        'id' => 'type_a',
        'label' => t('Bundle type A'),
    ));
    $enrollment_type->save();

    $enrollment_type = CourseEnrollmentType::create(array(
        'id' => 'type_b',
        'label' => t('Bundle type B'),
    ));
    $enrollment_type->save();

    // Add a field to course result and make it required for starting.
    $field_storagea = FieldStorageConfig::create([
        'id' => 'course_enrollment.enrollment_field_a',
        'field_name' => 'enrollment_field_a',
        'entity_type' => 'course_enrollment',
        'type' => 'string',
        'module' => 'core',
    ]);
    $field_storagea->save();
    $instancea = FieldConfig::create([
        'field_storage' => $field_storagea,
        'bundle' => 'type_a',
        'label' => 'Enrollment field A',
        'required' => TRUE,
        'field_name' => 'enrollment_field_a',
        'entity_type' => 'course_enrollment',
        'third_party_settings' => array(
          'course' => ['show_field' => TRUE],
        ),
    ]);
    $instancea->save();

    Drupal::service('entity_display.repository')->getFormDisplay('course_enrollment', 'type_a', 'default')
      ->setComponent('enrollment_field_a', array(
        'type' => 'text_textfield',
      ))
      ->save();

    // Add a field to course result and make it required for starting.
    $field_storageb = FieldStorageConfig::create([
        'id' => 'course_enrollment.enrollment_field_b',
        'field_name' => 'enrollment_field_b',
        'entity_type' => 'course_enrollment',
        'type' => 'string',
        'module' => 'core',
    ]);
    $field_storageb->save();
    $instanceb = FieldConfig::create([
        'field_storage' => $field_storageb,
        'bundle' => 'type_b',
        'label' => 'Enrollment field B',
        'required' => TRUE,
        'field_name' => 'enrollment_field_b',
        'entity_type' => 'course_enrollment', 'third_party_settings' =>
        array(
          'course' => ['show_field' => TRUE],
        ),
    ]);
    $instanceb->save();

    Drupal::service('entity_display.repository')->getFormDisplay('course_enrollment', 'type_b', 'default')
      ->setComponent('enrollment_field_b', array(
        'type' => 'text_textfield',
      ))
      ->save();

    $courseA = $this->createCourse(array('enrollment_type' => 'type_a'));
    $courseB = $this->createCourse(array('enrollment_type' => 'type_b'));
    $this->drupalLogin($this->student_user);

    // Check if field shows up and user is not yet enrolled.
    $this->drupalGet("course/{$courseA->id()}/take");
    $this->assertFieldById('edit-enrollment-field-a-0-value');
    $this->assertNoFieldById('edit-enrollment-field-b-0-value');
    $enrollment = $courseA->getEnrollment($this->student_user);
    $this->assertEmpty($enrollment);
    $this->drupalPostForm(NULL, array(), t('Save'));
    // Check that form API is working.
    $this->assertText('field is required');
    $this->drupalPostForm(NULL, array('enrollment_field_a[0][value]' => 'test 123'), t('Save'));

    // Check that a different field is on course B
    $this->drupalGet("course/{$courseB->id()}/take");
    $this->assertFieldById('edit-enrollment-field-b-0-value');
    $this->assertNoFieldById('edit-enrollment-field-a-0-value');

    // Mark field B to not show on enrollment.
    $instanceb->setThirdPartySetting('course', 'show_field', FALSE);
    $instanceb->save();
    $this->drupalGet("course/{$courseB->id()}/take");
    $this->assertNoFieldById('edit-enrollment-field-a-0-value');
    $this->assertNoFieldById('edit-enrollment-field-b-0-value');
  }

}
