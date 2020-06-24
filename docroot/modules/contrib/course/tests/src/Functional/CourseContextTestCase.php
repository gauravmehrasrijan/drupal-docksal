<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for Course context
 *
 * @group course
 */
class CourseContextTestCase extends CourseTestCase {

  /**
   * Testing finding course and objects via parameter search.
   */
  function testDetermineContext() {
    $course = $this->createCourse();

    // Create an object and give it an instance.
    $co1 = $this->createCourseObject($course);
    $co1->setInstanceId(1234)->save();

    // Find course object via cgco.
    $find_co1 = course_get_course_object('course_test_object', 1234);
    $this->assertEqual($co1->getId(), $find_co1->getId(), 'Found the same course object.');

    // Find the course via cdc.
    $find_course1 = course_determine_context('course_test_object', 1234);
    $this->assertEqual($find_course1->id(), $course->id(), 'Context came back to the same course.');
  }

  /**
   * Test objects that belong to multiple courses.
   */
  function testMultiContext() {
    $course1 = $this->createCourse();
    $course2 = $this->createCourse();

    // Create an object and give it an instance.
    $co1 = $this->createCourseObject($course1);
    $co1->setInstanceId(1234)->save();

    $co2 = $this->createCourseObject($course2);
    $co2->setInstanceId(1234)->save();

    // Now we have 2 course objects with the same instance but in different courses.
    // Simulate us taking one of the objects, and switch back and forth between courses.
    $co1->takeObject();
    $foundCourseNode1 = course_determine_context('course_test_object', 1234);
    $this->assertEqual($course1->id(), $foundCourseNode1->id(), 'Found the right course context, pass 1.');

    // Because this is a unit test the static cache is on. We have to flush it.
    drupal_static_reset('course_determine_context');

    // Taking with context set.
    $co2->takeObject();
    $foundCourseNode2 = course_determine_context('course_test_object', 1234);
    $this->assertEqual($course2->id(), $foundCourseNode2->id(), 'Found the right course context, pass 2.');

    // Because this is a unit test the static cache is on. We have to flush it.
    drupal_static_reset('course_determine_context');

    // Back to original context.
    $co1->takeObject();
    $foundCourseNode1 = course_determine_context('course_test_object', 1234);
    $this->assertEqual($course1->id(), $foundCourseNode1->id(), 'Found the right course context, pass 3.');

    // Because this is a unit test the static cache is on. We have to flush it.
    drupal_static_reset('course_determine_context');

    // Simulate a cold context check.
    unset($_SESSION['course']);
    $foundCourseNode1 = course_determine_context('course_test_object', 1234);
    $this->assertEqual($course1->id(), $foundCourseNode1->id(), 'Found the right course context, pass 4.');
  }

}
