<?php

namespace Drupal\Tests\course_book\Functional;

use Drupal;
use Drupal\course\Entity\CourseObject;
use Drupal\Tests\course\Functional\CourseTestCase;
use function node_access_rebuild;

/**
 * Tests books in courses.
 *
 * @group course_book
 */
class CourseObjectBookTestCase extends CourseTestCase {

  public static $modules = array('course', 'course_book');

  function testBookCourseObject() {
    // Create a course with 1 book.
    $course = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'book']);
    $co1->setOption('book_tracking', 'all');
    $co1->setCourse($course);
    $co1->save();
    $this->assertTrue($co1->getInstanceId() > 0, 'book node created on course object save.');

    // Add some more book pages.
    $bp1 = $this->drupalCreateNode(array(
      'type' => 'book',
      'book' => array(
        'bid' => $co1->getInstanceId(),
        'pid' => -1,
      ),
    ));

    $bp2 = $this->drupalCreateNode(array(
      'type' => 'book',
      'book' => array(
        'bid' => $co1->getInstanceId(),
        'pid' => -1,
      ),
    ));

    // Enroll the user in the course
    $course->enroll($this->student_user);

    // Test fulfillment tracking, set to view all pages before complete.
    $this->assertFalse($co1->getFulfillment($this->student_user)->isComplete(), 'Check that book object is not complete.');

    $this->drupalLogin($this->student_user);

    // Visit the book parent
    $this->drupalGet("node/" . $co1->getInstanceId());

    // Visit the first book page
    $this->drupalGet("node/{$bp1->id()}");

    // Test that course object is not yet complete.
    \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->resetCache();
    $this->assertFalse($co1->getFulfillment($this->student_user)->isComplete(), 'Check that book object is not complete after visiting 2/3 pages.');

    $this->drupalGet("node/{$bp2->id()}");
    \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->resetCache();
    $this->assertTrue($co1->getFulfillment($this->student_user)->isComplete(), 'Check that book object is now complete after visiting 3/3 pages.');
  }

  /**
   * Books have special behavior when it comes to content access. All the
   * sub pages should be protected.
   *
   * @todo this test is broken. Node access not working the same way from
   * within simpletest.
   */
  function testBookCourseObjectContentAccess() {
    $this->pass('Please fix me.');
    return;

    $this->drupalLogin($this->admin_user);

    // We just turned on node_access_book which requires us to rebuild node
    // access.
    node_access_rebuild();

    // Create a course with 1 book.
    $courseNode = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'book']);
    $co1->setCourse($courseNode->nid);
    // Set to private.
    $co1->setOption('private', 1);
    $co1->save();

    // Add some more book pages.
    $bp1 = $this->drupalCreateNode(array(
      'type' => 'book',
      'book' => array(
        'bid' => $co1->getInstanceId(),
      ),
    ));

    $bp2 = $this->drupalCreateNode(array(
      'type' => 'book',
      'book' => array(
        'bid' => $co1->getInstanceId(),
      ),
    ));

    // Simulate course_book_node_insert().
    $co1->save();

    // Hack to simluate what happens on the UI. This test does not work
    // correctly from simpletest for some reason.
    Drupal::database()->query("delete from {node_access} where realm = 'all'");

    // Try to visit the protected pages.
    $this->drupalLogin($this->student_user);
    $this->drupalGet("node/" . $co1->getInstanceId());
    $this->assertResponse(403);
    $this->drupalGet("node/$bp1->nid");
    $this->assertResponse(403);
    $this->drupalGet("node/$bp2->nid");
    $this->assertResponse(403);

    // Add a new page to the book, after it is already saved.
    $this->drupalLogin($this->admin_user);
    $bp3 = $this->drupalCreateNode(array(
      'type' => 'book',
      'book' => array(
        'bid' => $co1->getInstanceId(),
      ),
    ));

    // Simulate course_book_node_insert().
    $co1->save();

    // Hack to simluate what happens on the UI. This test does not work
    // correctly from simpletest for some reason.
    Drupal::database()->query("delete from {node_access} where realm = 'all'");

    // Check that the new book page also had it's ACL set up.
    $this->drupalLogin($this->student_user);
    $this->drupalGet("node/$bp3->nid");
    $this->assertResponse(403);

    // Enroll the user in the course and go to the first object.
    course_enroll($courseNode, $this->student_user);
    $this->drupalGet("node/{$courseNode->nid}/object/" . $co1->getId());

    // Make sure user can access all the sub-pages now.
    $this->drupalGet("node/$bp1->nid");
    $this->assertResponse(200);
    $this->drupalGet("node/$bp2->nid");
    $this->assertResponse(200);
    $this->drupalGet("node/$bp3->nid");
    $this->assertResponse(200);
  }

}
