<?php

namespace Drupal\Tests\course\Functional;

/**
 * Test class for dealing with adding and removing elements from the course
 * outline.
 *
 * @group course
 */
class CourseOutlineUiTestCase extends CourseTestCase {

  /**
   * Create a course object through the interface.
   *
   * @param stdClass $courseNode
   */
  function createCourseObjectUi($courseNode) {
    // Add a new course content object.
    $edit = array();
    $edit["more[object_type]"] = 'course_test_object';
    $this->drupalPostForm(NULL, $edit, t('Add object'));

    $this->assertText(t('Test course object'));
  }

  /**
   * Test creating a course object through the UI.
   */
  function testCourseOutlineCrud() {
    $course = $this->createCourse();
    $this->drupalGet("course/{$course->id()}/outline");
    $this->createCourseObjectUi($course);
    $this->clickLink('Settings');
  }

  /**
   * Test maximum course objects per course.
   */
  function testCourseOutlineMaxOccurences() {
    $course = $this->createCourse();
    $this->drupalGet("course/{$course->id()}/outline");
    $this->createCourseObjectUi($course);
    $this->createCourseObjectUi($course);

    $elements = $this->xpath('//select[@id=:id]//option[@value=:option]', array(':id' => 'edit-more-object-type', ':option' => 'course_test_object'));
    $this->assertTrue((bool) $elements, 'User able to add up to maxOccurances of course objects.');

    $this->createCourseObjectUi($course);

    $elements = $this->xpath('//select[@id=:id]//option[@value=:option]', array(':id' => 'edit-more-object-type', ':option' => 'course_test_object'));
    $this->assertFalse((bool) $elements, 'User was not able to add more than maxOccurances of course objects.');
  }

  /**
   * Test that an object can be marked for deletion from the course outline
   * without validation.
   */
  function testObjectDeletion() {
    $this->testCourseOutlineCrud();
    $this->drupalPostForm(NULL, [], t('edit-delete-button'));
    $this->assertText('Object will be removed from outline');
    $this->drupalGet("course/1/outline");
    $this->drupalPostForm(NULL, [], t('Save outline'));
    $this->assertNoText('Object will be removed from outline');
  }

}
