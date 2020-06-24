<?php

namespace Drupal\course_object_manual\Plugin\course\CourseObject;

use Drupal;
use Drupal\course\Entity\CourseObject;

/**
 * @CourseObject(
 *   id = "manual",
 *   label = "Manual step",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course\Entity\CourseObjectFulfillment"
 *   }
 * )
 */
class CourseObjectManual extends CourseObject {

  /**
   * Display status message as course content.
   */
  public function take() {
    return ['#plain_text' => $this->getStatus()];
  }

  /**
   * Return a message about the user's status in this object, for when this
   * object is hidden.
   */
  public function getStatus() {
    $user = Drupal::currentUser();
    $config = $this->getOptions();
    if ($this->getFulfillment($user)->isComplete()) {
      // User has a completed fulfillment (passed).
      return t($config['complete_msg']);
    }
    elseif ($this->getFulfillment($user)->getGrade() == '') {
      // User has a fulfillment but no grade (incomplete).
      return ($config['incomplete_msg']);
    }
    else {
      // User has a grade but not complete (failed).
      return $config['failed_msg'];
    }
  }

  public function optionsDefinition() {
    $defaults = parent::optionsDefinition();

    $defaults['complete_msg'] = 'Your instructor has marked you as passed.';
    $defaults['incomplete_msg'] = 'Your instructor has not given you a pass/fail grade yet.';
    $defaults['failed_msg'] = 'Your instructor has marked you as failed.';

    return $defaults;
  }

  public function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);

    $config = $this->getOptions();

    $form['complete_msg'] = array(
      '#type' => 'textfield',
      '#title' => t('Complete message'),
      '#default_value' => $config['complete_msg'],
    );

    $form['failed_msg'] = array(
      '#type' => 'textfield',
      '#title' => t('Failed message'),
      '#default_value' => $config['failed_msg'],
    );

    $form['incomplete_msg'] = array(
      '#type' => 'textfield',
      '#title' => t('Incomplete message'),
      '#default_value' => $config['incomplete_msg'],
    );
  }

}
