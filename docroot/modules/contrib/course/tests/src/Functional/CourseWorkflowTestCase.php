<?php

namespace Drupal\Tests\course\Functional;

use Drupal\course\Entity\CourseObject;

/**
 * Test class for the default outline and course workflow.
 *
 * @group course
 */
class CourseWorkflowTestCase extends CourseTestCase {

  public static $modules = ['course_test', 'block'];

  /**
   * Ensure the next object is properly linked.
   */
  function testObjectAdvancement() {
    // Add two course objects to a course.
    $course = $this->createCourse();

    $co1 = CourseObject::create(['object_type' => 'course_test_object']);
    $co1->setCourse($course);
    $co1->setOption('title', 'Course object 1');
    $co1->setOption('weight', 1);
    $co1->save();

    $co2 = CourseObject::create(['object_type' => 'course_test_object']);
    $co2->setCourse($course);
    $co2->setOption('title', 'Course object 2');
    $co2->setOption('weight', 2);
    $co2->save();

    $this->drupalGet("course/{$course->id()}/complete");
    $this->assertResponse(403, 'Cannot see completion page');

    // Login, enroll, and try to access the objects via links.
    $this->drupalLogin($this->student_user);
    $course->enroll($this->student_user);

    $this->drupalGet("course/{$course->id()}/complete");
    $this->assertResponse(200, 'Can see completion page');
    $this->assertText('This course is not complete.');

    $this->drupalGet("course/{$course->id()}/take");
    $this->assertLink('Course object 1');
    $this->assertNoLink('Course object 2');
    $this->assertNoLink('Next');
    $this->clickLink('Course object 1');
    $this->assertText(t('I am a test course object with the title @title', array('@title' => $co1->getOption('title'))));

    // Set the first object complete.
    $co1->getFulfillment($this->student_user)->setComplete(1)->save();
    $this->drupalGet("course/{$course->id()}/take");
    $this->assertLink('Course object 2');
    $this->assertLink('Next');
    $this->clickLink('Course object 2');
    $this->assertText(t('I am a test course object with the title @title', array('@title' => $co2->getOption('title'))));

    // Go back to object 1.
    $this->clickLink('Course object 1');
    $this->assertText(t('I am a test course object with the title @title', array('@title' => $co1->getOption('title'))));
    // Advance via "Next" link.
    $this->clickLink('Next');
    $this->assertText(t('I am a test course object with the title @title', array('@title' => $co2->getOption('title'))));

    // Set the second object complete.
    $co2->getFulfillment($this->student_user)->setComplete(1)->save();
    $this->drupalGet("course/{$course->id()}/take");
    $this->assertLink('Complete');
    $this->clickLink('Complete');
    $this->assertText('You have completed the course.');
  }

}
