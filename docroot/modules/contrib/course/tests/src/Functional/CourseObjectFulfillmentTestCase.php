<?php

namespace Drupal\Tests\course\Functional;

use Drupal\course\Entity\CourseObject;

/**
 * Description of CourseFulfillmentObjectTestCase
 *
 * @group course
 */
class CourseObjectFulfillmentTestCase extends CourseTestCase {

  /**
   * Test fulfillment of CourseObjects with an enrolled/unenrolled user
   */
  function testCourseContentObjectFulfillment() {
    // Add the course object to the course.
    $course = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'course_test_object']);
    $co1->setCourse($course);
    $co1->save();

    // Satisfy the object outside of the course.
    $co1->getFulfillment($this->student_user)->setComplete(TRUE)->save();

    $this->assertFalse($co1->getFulfillment($this->student_user)->isComplete(), 'Check that the object is not fulfilled.');

    // Enroll the user in the course.
    $course->enroll($this->student_user);

    // Satisfy the object inside of the course.
    $co1->getFulfillment($this->student_user)->setOption('test_value', 'findMe123')->setComplete(TRUE)->save();
    $co1->getFulfillment($this->student_user)->setOption('test_value_undef', 'findMe123')->setComplete(TRUE)->save();

    $this->assertEqual($co1->getFulfillment($this->student_user)->getOption('test_value'), 'findMe123', 'Check that defined fulfillment data was saved.');
    $this->assertNotEqual($co1->getFulfillment($this->student_user)->getOption('test_value_undef'), 'findMe123', 'Check that undefined fulfillment data was not saved.');
    $this->assertTrue($co1->getFulfillment($this->student_user)->isComplete(), 'Check that the object is fulfilled.');
  }

}
