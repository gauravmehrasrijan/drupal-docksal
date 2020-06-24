<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for Course grade.
 *
 * @group course
 */
class CourseGradeTestCase extends CourseTestCase {

  /**
   * Test that the final grade is calculated correctly.
   */
  public function testCourseFinalGrade() {
    $course = $this->createCourse();

    // Create a graded course object.
    $co1 = $this->createCourseObject($course, TRUE);

    $course->enroll($this->student_user);

    // Set grade result.
    $co1->getFulfillment($this->student_user)->set('grade_result', 80)->save();

    // Track object not included in final grade.
    $co1->set('grade_include', 0)->save();
    $co1->getCourse()->resetCache();
    $co1->getCourse()->getTracker($this->student_user)->track();
    $this->assertNotEqual($co1->getCourse()->getTracker($this->student_user)->get('grade_result')->getString(), 80, 'Course grade was not given from course object');

    // Track object included in final grade.
    $co1->set('grade_include', 1)->save();
    $co1->getCourse()->resetCache();
    $co1->getCourse()->getTracker($this->student_user)->track();
    $this->assertEqual($co1->getCourse()->getTracker($this->student_user)->get('grade_result')->getString(), 80, 'Course grade was given from course object');
  }

  /**
   * Test that the course grade access plugin functions properly.
   */
  public function testCourseGradeAccess() {
    $course = $this->createCourse();

    // Create a graded course objects.
    $this->createCourseObject($course, TRUE);

    // Add a non-graded course object.
    $this->createCourseObject($course);

    // Reload the course from DB.
    $courseObjects = array_values($course->getObjects());
    $co1 = $courseObjects[0];
    $co2 = $courseObjects[1];

    // Set object 1 to be included in the course grade.
    $co1->set('grade_include', TRUE)->save();
    $co1->getCourse()->resetCache();

    // Set object 2 to require a course grade of 80.
    $co2->addOptions(array(
      'plugins' => array(
        'access' => array(
          'grade' => array(
            'course_grade_range' => array(
              'low' => 80,
              'high' => 90
            )
          ),
        ),
      ),
    ))->save();

    // Enroll the user.
    $course->enroll($this->student_user);

    // Complete but don't hit the low grade requirement.
    $course->getObjects()[1]->getFulfillment($this->student_user)->setOption('grade_result', 79)->setComplete(1)->save();
    $course->getTracker($this->student_user)->track();
    $this->assertFalse($co2->access('take', $this->student_user), 'User cannot take course object with lower grade.');

    // Complete but don't hit the high grade requirement.
    $course->getObjects()[1]->getFulfillment($this->student_user)->setOption('grade_result', 91)->setComplete(1)->save();
    $course->getTracker($this->student_user)->track();
    $this->assertFalse($co2->access('take', $this->student_user), 'User cannot take course object with higher grade.');

    // Hit the grade requirement.
    $course->getObjects()[1]->getFulfillment($this->student_user)->setOption('grade_result', 80)->setComplete(1)->save();
    $course->getTracker($this->student_user)->track();
    $this->assertTrue($co2->access('take', $this->student_user), 'User can take course object with required grade.');
  }

}
