<?php

namespace Drupal\Tests\course\Functional;

/**
 * Tests for course object access.
 *
 * @group course
 */
class CourseObjectAccessTestCase extends CourseTestCase {

  use \Drupal\Core\Test\RefreshVariablesTrait;

  /**
   * Test sequential object access.
   */
  function testCourseObjectLinearWorkflow() {
    // Create a course with 4 objects.
    $course = $this->createCourse();
    for ($i = 1; $i <= 4; $i++) {
      $temp = $this->createCourseObject($course);
      $temp->setOption('title', "Object $i")->setOption('weight', $i)->save();
    }

    // Use the student user.
    $user = $this->student_user;

    /* @var $co \Drupal\course\Entity\CourseObject[] */
    $co = array_values($course->getObjects());

    // Student should not be able to access any objects.
    $this->assertFalse($co[0]->access('take', $user), 'Check that object 1 is not accessible.');
    $this->assertFalse($co[1]->access('take', $user), 'Check that object 2 is not accessible.');
    $this->assertFalse($co[2]->access('take', $user), 'Check that object 3 is not accessible.');
    $this->assertFalse($co[3]->access('take', $user), 'Check that object 4 is not accessible.');

    // (until they are enrolled)
    $course->enroll($user);


    // User should be able to access first object, but no others.
    $this->assertTrue($co[0]->access('take', $user), 'Check that object 1 is accessible after enrollment.');
    $this->assertFalse($co[1]->access('take', $user), 'Check that object 2 is not accessible.');
    $this->assertFalse($co[2]->access('take', $user), 'Check that object 3 is not accessible.');
    $this->assertFalse($co[3]->access('take', $user), 'Check that object 4 is not accessible.');

    // Set 1st object to complete.
    $co[0]->getFulfillment($user)->setComplete(1)->save();
    // Reset entity access cache.
    $this->assertTrue($co[1]->access('take', $user), 'Check that object 2 is accessible after object 1 is complete.');
    $this->assertFalse($co[2]->access('take', $user), 'Check that object 3 is not accessible after object 1 is complete.');
    $this->assertFalse($co[3]->access('take', $user), 'Check that object 4 is not accessible after object 1 is complete.');

    // Complete the rest of the course objects.
    $co[1]->getFulfillment($user)->setComplete(1)->save();
    $co[2]->getFulfillment($user)->setComplete(1)->save();
    $co[3]->getFulfillment($user)->setComplete(1)->save();

    // Reset entity access cache.
    $this->assertTrue($course->getTracker($user)->get('complete')->value, 'Check that course is complete.');
  }

  /**
   * Test an outline with both required and optional objects.
   */
  function testCourseObjectOptionalWorkflow() {
    // Create a course with 4 objects.
    $course = $this->createCourse();
    for ($i = 1; $i <= 4; $i++) {
      $temp = $this->createCourseObject($course);
      $temp->setOption('weight', $i)->save();
    }

    // Use the student user.
    $user = $this->student_user;
    $co = array_values($course->getObjects());

    $course->enroll($user);

    // Set all optional except the second object.
    $co[0]->setOption('required', 0);
    $co[1]->setOption('required', 1);
    $co[2]->setOption('required', 0);
    $co[3]->setOption('required', 0);

    $this->assertFalse($co[2]->access('take', $user) || $co[3]->access('take', $user), 'User is blocked by second required object to get to fourth optional object.');

    $this->assertTrue($co[0]->access('take', $user), 'User has access to first optional object.');
    $this->assertTrue($co[1]->access('take', $user), 'User has access to second required object.');

    // Complete the only required object.
    $co[1]->getFulfillment($user)->setComplete(1)->save();
    $this->assertTrue($co[2]->access('take', $user) && $co[3]->access('take', $user), 'User granted access to remaining optional objects.');
  }

  /**
   * Test an outline with skippable required objects.
   */
  function testCourseSkippableRequiredObjectsWorkflow() {
    // Create a course with 4 objects.
    $course = $this->createCourse();
    for ($i = 1; $i <= 4; $i++) {
      $temp = $this->createCourseObject($course);
      $temp->setOption('weight', $i)->save();
    }

    // Use the student user.
    $user = $this->student_user;

    $co = array_values($course->getObjects());

    $course->enroll($user);

    // Set all required, but make the second one skippable.
    $co[0]->setOption('required', 1);
    $co[1]->setOption('required', 1);
    $co[1]->setOption('skippable', 1)->save();
    $co[2]->setOption('required', 1);
    $co[3]->setOption('required', 1);

    // We should not be able to bypass the first object.
    $this->assertTrue($co[0]->access('take', $user), 'User can access first object.');
    $this->assertFalse($co[1]->access('take', $user), 'User is blocked from second object.');
    // Complete the first object.
    $co[0]->getFulfillment($user)->setComplete(1)->save();
    $this->assertTrue($co[1]->access('take', $user), 'User can access second object.');

    // Now that the user can access the second object, because it is required,
    // and skippable, they should be able to access the third object.
    $this->assertTrue($co[2]->access('take', $user), 'User can access third object.');
    // However because the third object is required, they cannot acess the
    // fourth.
    $this->assertFalse($co[3]->access('take', $user), 'User is blocked from fourth object.');

    // Complete all the required, but not skippable objects.
    $co[2]->getFulfillment($user)->setComplete(1)->save();
    $co[3]->getFulfillment($user)->setComplete(1)->save();

    // Even though all required, non-skippable objects were completed, the
    // course is not complete. The skippable object must still be completed.
    $this->assertFalse($course->getTracker($user)->get('complete')->value, 'Check that course is not complete.');

    $co[1]->getFulfillment($user)->setComplete(1)->save();
    $this->assertTrue($course->getTracker($user)->get('complete')->value, 'Check that course is complete.');
  }

  /**
   * Test hidden course objects do not show up in the course outline but block
   * completion.
   */
  function testHiddenCourseObjects() {
    // Create a course.
    $course = $this->createCourse();

    // Use the student user.
    $user = $this->student_user;
    $this->drupalLogin($user);

    // Create an optional object so we can test the "Next" button.
    $o_optional = $this->createCourseObject($course);
    $o_optional->set('required', FALSE)->save();

    $o1 = $this->createCourseObject($course);
    $o1->set('required', TRUE)->save();

    // By default, should be visible in the outline.
    $this->assertTrue($o1->access('see', $user));

    // Make object hidden.
    $o1->set('hidden', 1)->save();
    $this->assertFalse($o1->access('see', $user));
    $this->assertFalse($o1->access('take', $user));
    $this->assertFalse($o1->access('view', $user));

    $this->drupalGet("course/{$course->id()}/take");
    $this->drupalGet("course/{$course->id()}/object/{$o_optional->id()}");
    $this->assertNoLink(t('Next'));

    $this->drupalGet("course/{$course->id()}/object/{$o1->id()}");
    $this->assertResponse(403, t('Hidden object is not accessible.'));

    // Check that the course is not complete without completing the hidden object.
    $report = $course->getEnrollment($user);
    $this->assertFalse($report->get('complete')->value);
  }

  /**
   * Test disabled course objects do not show up in the course outline and do
   * not block completion.
   */
  function testDisabledCourseObjects() {
    // Create a course.
    $course = $this->createCourse();

    // Use the student user.
    $user = $this->student_user;
    $this->drupalLogin($user);

    // Create an optional object so we can test the "Next" button.
    $o_optional = $this->createCourseObject($course);
    $o_optional->set('required', FALSE)->save();

    $o1 = $this->createCourseObject($course);
    $o1->set('required', TRUE)->save();

    // By default, should be visible.
    $this->assertTrue($o1->access('see', $user));

    // Make object disabled.
    $o1->set('enabled', 0)->save();
    $this->assertFalse($o1->access('see', $user));
    $this->assertFalse($o1->access('view', $user));
    $this->assertFalse($o1->access('take', $user));
    $this->drupalGet("course/{$course->id()}/take");

    // Completion link.
    $this->clickLink(t('Next'));
    $this->assertResponse(200, t('Did not get access denied.'));

    $this->drupalGet("course/{$course->id()}/object/{$o1->id()}");
    $this->assertResponse(403, t('Disabled object is not accessible.'));

    // Check that the course is complete even without completing the disabled
    // object.
    $report = $course->getEnrollment($user);
    $this->assertTrue($report->get('complete')->value);

    // Set the disabled object to be at the beginning of the course.
    $o1->set('weight', -100)->save();
    // Try with a new user.
    $this->student_user = $this->createStudentUser();
    $this->drupalLogin($this->student_user);
    $this->drupalGet("course/{$course->id()}/take");

    $this->drupalGet("course/{$course->id()}/object/{$o1->id()}");
    $this->assertResponse(403, t('Disabled first object is not accessible.'));

    $this->drupalGet("course/{$course->id()}/object/{$o_optional->id()}");
    $this->assertResponse(200, t('Second object is accessible.'));
  }

}
