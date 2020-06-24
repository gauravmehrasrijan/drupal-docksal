<?php

namespace  Drupal\Tests\course_content\Functional;

use Drupal\course\Entity\CourseObject;
use Drupal\node\Entity\Node;
use Drupal\Tests\course\Functional\CourseTestCase;

/**
 * Tests content in courses.
 *
 * @group course_content
 */
class CourseObjectContentTestCase extends CourseTestCase {

  public static $modules = array('course', 'course_content');

  /**
   * Test course content object creation.
   */
  function testContentCourseObjectCreation() {
    $ct1 = $this->drupalCreateContentType();
    $ct1->setThirdPartySetting('course_content', 'use', 1);
    $ct1->save();

    // Save a new object, which should create a node with our new content type.
    $course = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'content']);
    $co1->setCourse($course->id());
    $co1->save();

    // Reload course.
    foreach ($course->getObjects() as $courseObject) {
      $node = Node::load($courseObject->getInstanceId());
      $this->assertEqual($node->bundle(), $ct1->id(), "Node type saved is the same node type specified by the course object.");
    }
  }

}
