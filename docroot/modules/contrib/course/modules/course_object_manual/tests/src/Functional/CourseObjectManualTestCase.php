<?php

/**
 * Tests quizzes in courses.
 *
 * @group course_object_manual
 */
class CourseObjectManualTestCase extends CourseObjectTestCase {

  public static function getInfo() {
    // Note that getInfo() strings are not translated with t().
    return array(
      'name' => 'Course object manual',
      'description' => 'Ensure that manual course objects function properly.',
      'group' => 'Course',
    );
  }

  public function getModules() {
    $modules = parent::getModules();
    $modules[] = 'course_object_manual';
    return $modules;
  }

  /**
   * Test manual course object functionality.
   */
  public function testCourseObjectManual() {
    $account = $this->drupalCreateUser();

    // Set up a manual course object.
    $node = $this->createCourse();
    $course = course_get_course($node);
    $co1 = course_get_course_object('course_object_manual', 'manual', NULL, $course);
    $co1->save();

    // Get the course object in the context of the user.
    $co1 = course_get_course_object_by_id($co1->getId());
    $this->drupalLogin($account);
    course_enroll($node, $account);
    $this->drupalGet($co1->getUrl());
    $this->assertText('Your instructor has not given you a pass/fail grade yet.');

    // Test fail.
    $co1 = course_get_course_object_by_id($co1->getId());
    $co1->getFulfillment($account)->setGrade(0)->save();
    $this->drupalGet($co1->getUrl());
    $this->assertText('Your instructor has marked you as failed.');

    // Test passing.
    $co1 = course_get_course_object_by_id($co1->getId());
    $co1->getFulfillment($account)->setGrade(100)->setComplete(1)->save();
    $this->drupalGet($co1->getUrl());
    $this->assertText('Your instructor has marked you as passed.');
  }

}
