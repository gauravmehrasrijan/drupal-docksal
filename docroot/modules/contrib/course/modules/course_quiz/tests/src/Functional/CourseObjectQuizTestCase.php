<?php

namespace Drupal\Tests\course_quiz\Functional;

use Drupal\course\Entity\CourseObject;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\course\Functional\CourseTestCase;

/**
 * Tests quizzes in courses.
 *
 * @group course_quiz
 */
class CourseObjectQuizTestCase extends CourseTestCase {

  /**
   * @todo Remove once there is 8.x-3.0-alpha6 which fixes a schema issue.
   *
   * @see ConfigSchemaChecker
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;
  public static $modules = ['course_quiz', 'quiz_truefalse'];

  function setUp() {
    parent::setUp();

    $this->quiz_admin = $this->drupalCreateUser(array('access quiz', 'create truefalse quiz_question', 'update truefalse quiz_question', 'create quiz quiz', 'update any quiz quiz'));
  }

  function testQuizCourseObject() {
    $this->drupalLogin($this->quiz_admin);
    // Create a course with 1 quiz.
    $course = $this->createCourse();
    $co1 = CourseObject::create(['object_type' => 'quiz']);
    $co1->setCourse($course->id());
    $co1->setOption('passing_grade', 100);
    $co1->save();
    $this->assertTrue($co1->getInstanceId() > 0, 'Quiz node created on course object save.');

    $quiz = Quiz::load($co1->getInstanceId());
    $this->assertNotEmpty($quiz);

    $quiz_question = QuizQuestion::create(['type' => 'truefalse', 'truefalse_correct' => 1]);
    $quiz_question->save();

    $quiz->addQuestion($quiz_question);

    // Enroll the user in the course
    $course->enroll($this->quiz_admin);

    // Fail the quiz.
    $this->drupalGet($quiz->toUrl('take'));
    $this->drupalPostForm(NULL, array(
      'question[1][answer]' => 0,
      ), t('Finish'));
    \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->resetCache();
    $this->assertFalse($co1->getFulfillment($this->quiz_admin)->isComplete(), 'Check that quiz fulfillment is not complete after fail.');

    // Pass the quiz.
    $this->drupalGet($quiz->toUrl('take'));
    $this->drupalPostForm(NULL, array(
      'question[1][answer]' => 1,
      ), t('Finish'));
    \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->resetCache();
    $this->assertTrue($co1->getFulfillment($this->quiz_admin)->isComplete(), 'Check that quiz fulfillment is complete.');
  }

}
