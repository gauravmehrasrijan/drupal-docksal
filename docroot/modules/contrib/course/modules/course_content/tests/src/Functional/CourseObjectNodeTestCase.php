<?php

namespace Drupal\Tests\course_content\Functional;

use Drupal\course\Entity\CourseObject;
use Drupal\Tests\course\Functional\CourseTestCase;

/**
 * Test node-based course object functionality.
 *
 * @group course
 */
class CourseObjectNodeTestCase extends CourseTestCase {

  public static $modules = array('course_content', 'course_test');

  /**
   * Test content privacy for node based course objects.
   */
  function testContentAccess() {
    $course = $this->createCourse();

    // Create the course object using the provided "course_test_content"
    // content type.
    $co1 = CourseObject::create([
        'object_type' => 'content',
        'cid' => $course->id(),
    ]);
    $co1->set('private', 1);
    $co1->save();

    $this->drupalLogin($this->student_user);
    $course->enroll($this->student_user);

    $this->drupalGet("node/" . $co1->getInstanceId());
    $this->assertResponse(403, 'Check that node is protected outside the course.');

    // Save new fulfillment so they can access the linked content.
    $cof = $co1->getFulfillment($this->student_user);
    $cof->save();
    $this->drupalGet("node/" . $co1->getInstanceId());
    $this->assertResponse(200, 'Check that node is accessible when user enters course object.');

    // Delete fulfillment so they can no longer access the linked content.
    //$cof->delete();
    // Why is this necessary. Issue with static cache.
    $fulfillments = \Drupal\course\Entity\CourseObjectFulfillment::loadMultiple();
    \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->delete($fulfillments);
    $this->drupalGet("node/" . $co1->getInstanceId());
    $this->assertResponse(403, 'Check that node is protected outside the course, after revoke.');
  }

}
