<?php

namespace Drupal\Tests\course\Functional;

use Drupal\course\Entity\Course;
use Drupal\Tests\BrowserTestBase;
use stdClass;
use function course_get_course_object;

/**
 * Master class for Course tests.
 */
abstract class CourseTestCase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->admin_user = $this->drupalCreateUser(array('bypass node access', 'administer course'));
    $this->student_user = $this->createStudentUser();
    $this->drupalLogin($this->admin_user);
  }

  protected function createStudentUser() {
    return $this->drupalCreateUser(array('take course', 'view course', 'enroll course'));
  }

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = array('course_test');

  /**
   * Create a course node using the test content type.
   *
   * @return stdClass
   */
  function createCourse(array $extra = array()) {
    $defaults = array(
      'outline' => 'course',
    );
    $course = Course::create(array_merge_recursive($defaults, $extra));
    $course->save();
    return $course;
  }

  /**
   * Create a new persistent course object on a Course.
   *
   * @param Course $course
   * @return Course
   */
  function createCourseObject(Course $course, $graded = FALSE) {
    static $weight = 0;
    if ($graded) {
      $courseObject = \Drupal\course\Entity\CourseObject::create(['object_type' => 'course_test_graded_object']);
    }
    else {
      $courseObject = \Drupal\course\Entity\CourseObject::create(['object_type' => 'course_test_object']);
    }
    $courseObject->setCourse($course);
    $courseObject->setOption('weight', $weight++);
    $courseObject->save();
    return $courseObject;
  }

}
