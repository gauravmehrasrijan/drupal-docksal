<?php

namespace Drupal\Tests\course_webform\Functional;

use Drupal\course\Entity\CourseObject;
use Drupal\Tests\course\Functional\CourseTestCase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests webforms in courses.
 *
 * @group course_webform
 */
class CourseObjectWebformTestCase extends CourseTestCase {

  public static $modules = ['course_webform'];

  function setUp() {
    parent::setUp();
    $perms = array('create webform', 'edit any webform');
    $this->webform_admin = $this->drupalCreateUser($perms);
  }

  function testWebformCourseObject() {
    $this->drupalLogin($this->webform_admin);
    // Create a course with 1 webform.
    $course = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'webform']);
    $co1->setCourse($course->id());
    $co1->save();
    $webform = Webform::load($co1->getInstanceId());
    $this->assertSession()->assert($webform->id(), 'Webform created on course object save.');


    // Allow drafts
    $webform->setSetting('draft', 'authenticated');

    // Build a render array of elements.
    $elements = [
      'test' => [
        '#type' => 'textfield',
        '#title' => 'Test',
      ],
    ];
    $webform->setElements($elements);
    $webform->save();

    $this->drupalLogin($this->student_user);

    // Enroll the user in the course
    $course->enroll($this->student_user);

    // Take the webform.
    $this->drupalGet($webform->toUrl());
    $this->assertFalse((bool) $co1->getFulfillment($this->student_user)->isComplete(), 'Check that webform is not completed yet.');

    // Draft the webform.
    $this->drupalPostForm(NULL, array(
      'test' => 1,
      ), t('Save Draft'));

    $sid = $co1->getFulfillment($this->student_user)->getInstanceId();
    $this->assertEmpty($sid, 'Check that webform submission was not recorded.');
    $this->assertEmpty($co1->getFulfillment($this->student_user)->isComplete(), 'Check that webform is not complete.');

    // Finish the webform.
    $this->drupalPostForm(NULL, array(
      'test' => 1,
      ), t('Submit'));

    $sid = $co1->getFulfillment($this->student_user)->getInstanceId();
    $this->assertNotEmpty($sid, 'Check that webform submission was recorded.');
    $this->assertNotEmpty($co1->getFulfillment($this->student_user)->isComplete(), 'Check that webform is completed.');

    // Test that on unenroll, the user's webform submission is deleted.
    $co1->getFulfillment($this->student_user)->delete();
    $this->refreshVariables();
    $submission = WebformSubmission::load($sid);
    $this->assertEmpty($submission, 'Check that webform submission was deleted.');
  }

}
