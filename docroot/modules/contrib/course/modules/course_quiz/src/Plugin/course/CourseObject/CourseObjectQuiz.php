<?php

namespace Drupal\course_quiz\Plugin\course\CourseObject;

use Drupal;
use Drupal\course\Entity\CourseObject;
use Drupal\quiz\Entity\Quiz;
use stdClass;
use function module_load_include;
use function quiz_stats_get_adv_stats;
use function views_embed_view;

/**
 * @CourseObject(
 *   id = "quiz",
 *   label = "Quiz",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_quiz\Plugin\course\CourseObject\CourseObjectQuizFulfillment"
 *   }
 * )
 */
class CourseObjectQuiz extends CourseObject {

  /**
   * Course context handler callback.
   */
  public static function context() {
    $route_match = Drupal::routeMatch();
    if (in_array($route_match->getRouteName(), ['entity.quiz.canonical', 'quiz.take', 'quiz.question.feedback', 'quiz.question.take'])) {
      $quiz = $route_match->getParameter('quiz');

      if ($courseObject = course_get_course_object('quiz', $quiz->id())) {
        return array(
          'object_type' => 'quiz',
          'instance' => $quiz->id(),
        );
      }
    }
  }

  /**
   * Create the quiz node and set it as this object's instance.
   */
  function createInstance() {
    $quiz = Quiz::create(['type' => 'quiz']);
    $quiz->save();
    $this->setInstanceId($quiz->id());
  }

  public function getTakeType() {
    return 'redirect';
  }

  /**
   * The take URL of the quiz is /take.
   */
  function getTakeUrl() {
    if ($this->getOption('quiz_goto') == "view") {
      return \Drupal\Core\Url::fromRoute('entity.quiz.take', ['quiz' => $this->getInstanceId()]);
    }
    else {
      return \Drupal\Core\Url::fromRoute('entity.quiz.canonical', ['quiz' => $this->getInstanceId()]);
    }
  }

  /**
   * Course quiz options.
   */
  public function optionsDefinition() {
    $options = parent::optionsDefinition();

    $options['quiz_goto'] = 'view';
    $options['passing_grade'] = 75;

    return $options;
  }

  /**
   * Add an option only pertinent to quiz?
   */
  public function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);
    $defaults = $this->getOptions();

    $form['instance'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'quiz',
      '#default_value' => $this->getOption('instance') ? Quiz::load($this->getOption('instance')) : NULL,
    );

    $form['quiz_goto'] = array(
      '#type' => 'select',
      '#title' => t('Quiz entry point'),
      '#options' => array(
        'view' => t('View Quiz'),
        'take' => t('Take Quiz'),
      ),
      '#default_value' => $defaults['quiz_goto'],
      '#description' => t('Selecting "Take Quiz" will launch the user directly into taking the quiz, without viewing the quiz body.'),
    );

    $form['grading']['passing_grade'] = array(
      '#title' => t('Passing grade'),
      '#type' => 'textfield',
      '#size' => 4,
      '#default_value' => $defaults['passing_grade'],
      '#description' => t('The user will not be able to proceed past this object unless this grade is met.'),
    );
  }

  /**
   * Let the user know if they have a Quiz without questions.
   */
  public function getWarnings() {
    $warnings = parent::getWarnings();

    if ($this->getInstanceId()) {
      $quiz = Drupal\quiz\Entity\Quiz::load($this->getInstanceId());
      if (!$quiz->getNumberOfQuestions()) {
        $link = Drupal\Core\Link::createFromRoute('add questions', "quiz.questions", ['quiz' => $this->getInstanceId()]);
        $warnings[] = t('This Quiz does not have any questions. Please @link.', array('@link' => $link->toString()));
      }
    }

    return $warnings;
  }

  public function getReports() {
    $reports = parent::getReports();
    $reports['results'] = array(
      'title' => t('Results'),
    );
    if (Drupal::moduleHandler()->moduleExists('quiz_stats')) {
      $reports['statistics'] = array(
        'title' => t('Statistics'),
      );
    }
    return $reports;
  }

  public function getReport($key) {
    module_load_include('inc', 'quiz', 'quiz.admin');
    switch ($key) {
      case 'results':
        if (course_quiz_quiz_version() >= 5) {
          $out = views_embed_view('quiz_results', 'default', $this->getInstanceId());
        }
        else {
          $out = drupal_get_form('quiz_results_manage_results_form', $this->getNode());
        }
        return array(
          'title' => t('Quiz results'),
          'content' => $out,
        );
      case 'statistics':
        module_load_include('inc', 'quiz_stats', 'quiz_stats.admin');
        return array(
          'title' => t('Quiz statistics'),
          'content' => quiz_stats_get_adv_stats($this->getNode()->vid),
        );
    }
    return parent::getReport($key);
  }

  function getNodeTypes() {
    return array('quiz');
  }

  function isGraded() {
    return TRUE;
  }

  function getCloneAbility() {
    return t('%object can only be partially cloned. It will be created with the same settings, but without the questions.', array('%object' => $this->getTitle()));
  }

  function getOptionsSummary() {
    $summary = parent::getOptionsSummary();
    if ($this->getInstanceId()) {
      $link = Drupal\Core\Link::createFromRoute('Edit questions', "quiz.questions", ['quiz' => $this->getInstanceId()]);
      $summary['questions'] = $link->toString();
    }
    return $summary;
  }

  /**
   * Get the status of this quiz for the requirements list.
   */
  function getStatus() {
    $account = Drupal::currentUser();
    $grade = $this->isGraded() ? t('Your grade: %grade_result%<br/>Pass grade: %passing_grade%', array(
        '%grade_result' => $this->getFulfillment($account)->getOption('grade_result'),
        '%passing_grade' => $this->getOption('passing_grade'),
      )) : '';
    return $grade;
  }

  /**
   * Course node context handler callback.
   *
   * If this question is part of a quiz in a course, what quizzes do we belong
   * to?
   */
  public static function getNodeInstances($node) {
    $quizzes = array();

    // Finding quizzes this question already belongs to.
    $sql = 'SELECT n.nid, r.parent_vid AS vid, n.title FROM {quiz_node_relationship} r
            JOIN {node} n ON n.nid = r.parent_nid
            WHERE r.child_vid = :child_vid
            ORDER BY r.parent_vid DESC';
    $res = Drupal::database()->query($sql, array(':child_vid' => $node->vid));
    while ($row = $res->fetch()) {
      $quizzes[] = $row->nid;
    }

    return $quizzes;
  }

}
