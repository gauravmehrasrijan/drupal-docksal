<?php

namespace Drupal\course_poll\Course\Object;

use Drupal\course\Object\CourseObjectNode;

/**
 * Parent class for poll tracking.
 *
 * @ContentEntityBundleClass(
 *   label = "Specialized paragraph",
 *   entity_type = "paragraph",
 *   bundle = "specialized_bundle"
 * )
 */
class CourseObjectPoll extends CourseObjectNode {

  function getTakeType() {
    return 'redirect';
  }

  public function optionsDefinition() {
    $options = parent::optionsDefinition();
    $options['use_node_title'] = 1;
    return $options;
  }

  /**
   * @todo broken D8
   */
  public function Xcreate($node = NULL) {
    $poll = new stdClass;
    $poll->choice = array(
      array(
        'chtext' => 'Yes',
        'chvotes' => 0,
        'weight' => 0,
      ),
      array(
        'chtext' => 'No',
        'chvotes' => 0,
        'weight' => 0,
      ),
    );
    $poll->type = 'poll';
    $poll->active = 1;
    $poll->runtime = 0;
    parent::create($poll);
  }

  public function getReports() {
    $reports = parent::getReports();
    $reports += array(
      'results' => array(
        'title' => 'Results',
      ),
      'all' => array(
        'title' => 'List votes',
      ),
    );
    return $reports;
  }

  public function getReport($key) {
    module_load_include('inc', 'poll', 'poll.pages');
    switch ($key) {
      case 'results':
        return array(
          'title' => $this->getNode()->title,
          'content' => poll_view_results($this->getNode(), NULL, NULL, NULL),
        );
      case 'all':
        $out = poll_votes($this->getNode());
        return array(
          'title' => 'All votes',
          'content' => drupal_render($out),
        );
    }
    return parent::getReport($key);
  }

  function getNodeTypes() {
    return array('poll');
  }

  function getCloneAbility() {
    return TRUE;
  }

}
