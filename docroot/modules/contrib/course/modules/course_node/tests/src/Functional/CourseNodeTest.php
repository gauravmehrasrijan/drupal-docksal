<?php

namespace Drupal\Tests\course_node\Functional;

use Drupal\Tests\course\Functional\CourseTestCase;

/**
 * Test course node functionality.
 *
 * @group course
 */
class CourseNodeTestCase extends CourseTestCase {

  public static $modules = ['course_node'];

  function testCourseNode() {
    $this->drupalLogin($this->admin_user);

    $this->drupalGet("node/add/course");
    $this->drupalPostForm(NULL, [
      'title[0][value]' => 'Course page title 1',
      'course[form][inline_entity_form][title][0][value]' => 'Course title 1',
      ], t('Save'));


    $this->drupalLogin($this->student_user);
    $this->drupalGet('node/1');
    $this->clickLink('Course title 1');
    $this->clickLink('Take course');
    $this->clickLink('Complete');
  }

}
