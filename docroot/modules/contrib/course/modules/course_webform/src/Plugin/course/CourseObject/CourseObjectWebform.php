<?php

namespace Drupal\course_webform\Plugin\course\CourseObject;

use Drupal;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\course\Entity\CourseObject;
use Drupal\webform\Entity\Webform;
use function course_get_course_object;
use function drupal_render;
use function module_load_include;

/**
 * @CourseObject(
 *   id = "webform",
 *   label = "Webform",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_webform\Plugin\course\CourseObject\CourseObjectWebformFulfillment"
 *   }
 * )
 */
class CourseObjectWebform extends CourseObject {

  /**
   * {@inheritdoc}
   */
  public function createInstance() {
    $webform = Webform::create(['id' => 'course_object_' . $this->id()]);
    $webform->save();
    $this->setInstanceId($webform->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getWarnings() {
    $warnings = parent::getWarnings();

    $webform = Webform::load($this->getInstanceId());

    if ($this->getInstanceId() && !$webform->getElementsDecoded()) {
      $link = Link::createFromRoute(t('add questions'), 'entity.webform.edit_form', [
          'webform' => $webform->id(),
        ])->toString();
      $warnings[] = t('The Webform has no questions. Please @link.', array('@link' => $link));
    }

    return $warnings;
  }

  /**
   * {@inheritdoc}
   */
  public function getReports() {
    $reports = parent::getReports();
    $reports += array(
      'submissions' => array(
        'title' => t('Submissions'),
      ),
      'analysis' => array(
        'title' => t('Analysis'),
      ),
      'download' => array(
        'title' => t('Download'),
      ),
    );
    return $reports;
  }

  /**
   * {@inheritdoc}
   */
  public function getReport($key) {
    module_load_include('inc', 'webform', 'includes/webform.report');
    switch ($key) {
      case 'submissions':
        return array(
          'title' => t('Webform results'),
          'content' => webform_results_submissions($this->getNode(), FALSE, 50),
        );
      case 'analysis':
        return array(
          'title' => t('Webform results'),
          'content' => webform_results_analysis($this->getNode()),
        );
      case 'download':
        $out = drupal_get_form('webform_results_download_form', $this->getNode());
        return array(
          'title' => t('Webform results'),
          'content' => drupal_render($out),
        );
    }
    return parent::getReport($key);
  }

  /**
   * {@inheritdoc}
   */
  function getCloneAbility() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  function getOptionsSummary() {
    $summary = parent::getOptionsSummary();
    if ($this->getInstanceId()) {
      $link = Link::createFromRoute(t('Edit questions'), 'entity.webform.edit_form', [
          'webform' => $this->getInstanceId(),
        ])->toString();
      $summary['questions'] = $link;
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);

    $form['instance'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'webform',
      '#default_value' => $this->getOption('instance') ? Webform::load($this->getOption('instance')) : NULL,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function getTakeUrl() {
    $url = Url::fromRoute('entity.webform.canonical', ['webform' => $this->getInstanceId()]);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getTakeType() {
    return 'redirect';
  }

  /**
   * {@inheritdoc}
   */
  public static function context() {
    $route_match = Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.webform.canonical') {
      $webform = $route_match->getParameter('webform');

      if ($courseObject = course_get_course_object('webform', $webform->id())) {
        return array(
          'object_type' => 'webform',
          'instance' => $webform->id(),
        );
      }
    }
  }

}
