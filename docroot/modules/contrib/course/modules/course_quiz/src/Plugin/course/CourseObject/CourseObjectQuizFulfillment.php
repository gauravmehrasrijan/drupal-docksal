<?php

namespace Drupal\course_quiz\Plugin\course\CourseObject;

use Drupal\course\Entity\CourseObjectFulfillment;
use Drupal\quiz\Entity\QuizResult;
use function entity_delete_multiple;

/**
 * Course fulfillment class for quizzes.
 */
class CourseObjectQuizFulfillment extends CourseObjectFulfillment {

  /**
   * Define storage for quiz result IDs.
   */
  function optionsDefinition() {
    return array('quiz_result_ids' => array());
  }

  /**
   * Remove all quiz attempts associated with this fulfillment.
   */
  function delete() {
    parent::delete();
    $result_ids = (array) $this->getOption('quiz_result_ids');
    entity_delete_multiple('quiz_result', $result_ids);
  }

  /**
   * Marks a user's fulfillment record for this object complete if the user
   * passed the quiz.
   */
  function grade(QuizResult $quiz_result) {

    // Store the result ID.
    $result_ids = (array) $this->getOption('quiz_result_ids');
    $result_ids[] = $quiz_result->id();
    $this->setOption('instance', $quiz_result->id());
    $this->setOption('quiz_result_ids', $result_ids);

    if ($quiz_result && ($quiz_result->get('score')->getString() >= $this->getCourseObject()->getOption('passing_grade'))) {
      $this->setGrade($quiz_result->get('score')->getString())->setComplete()->save();
    }
    else {
      $this->setGrade($quiz_result->get('score')->getString())->save();
    }
  }

}
