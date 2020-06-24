<?php

namespace Drupal\Tests\course\Functional;

/**
 * Description of CourseObjectTestCase
 *
 * @group course
 */
class CourseObjectTestCase extends CourseTestCase {

  /**
   * Test basic save/load of CourseObjects.
   */
  function testCourseObjectBasicCrud() {
    $course = $this->createCourse();

    // Create the course object
    $courseObject = $this->createCourseObject($course);

    // Make sure the object saved.
    $this->assertTrue($courseObject->getId() > 0, 'Course object received ID.');

    $id = $courseObject->getId();

    // Load by coid
    $courseObject = course_get_course_object_by_id($id);
    $this->assertTrue($courseObject->getId() == $id, 'Loaded course object by ID.');

    // Delete
    $courseObject->delete();
    $courseObject = course_get_course_object_by_id($id);
    $this->assertFalse($courseObject, 'Check that deleted object no longer exists.');
  }

  /**
   * Test CourseObject configurations.
   */
  function testCourseObjectConfigurations() {
    $courseNode = $this->createCourse();
    $co1 = $this->createCourseObject($courseNode);

    $co1->setOption('test_option', 'FIND_ME');
    $co1->save();
    $id = $co1->getId();

    $co2 = course_get_course_object_by_id($id);
    $this->assertEqual($co2->getOption('test_option'), 'FIND_ME', 'Check that options save and load successfully.');
  }

  /**
   * Test the construction of CourseObjects.
   *
   * @todo is this necessary any more? Disabling test.
   */
  function xtestCourseObjectConstruction() {
    $course = $this->createCourse();
    $this->createCourseObject($course);

    $courseObjects = array_values($course->getObjects());
    $courseObject = reset($courseObjects);
    $getCourse = $courseObject->getCourse();

    $this->assertEqual(spl_object_hash($course), spl_object_hash($getCourse), 'Check that Courses inside of CourseObjects inside of Course are the same.');
  }

}
