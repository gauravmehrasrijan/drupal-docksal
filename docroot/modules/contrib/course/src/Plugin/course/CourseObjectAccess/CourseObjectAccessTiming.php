<?php

namespace Drupal\course\Plugin\course\CourseObjectAccess;

use Drupal;
use Drupal\course\Plugin\CourseObjectAccessPluginBase;

/**
 * @CourseObjectAccess(
 *   id = "timing",
 *   label = @Translation("Timing"),
 * )
 */
class CourseObjectAccessTiming extends CourseObjectAccessPluginBase {

  public function optionsDefinition() {
    $defaults = parent::optionsDefinition();

    $defaults += array(
      'duration' => NULL,
      'release' => NULL,
      'expiration' => NULL,
      'release_hidden' => NULL,
      'expiration_hidden' => NULL,
    );

    return $defaults;
  }

  function take($account) {
    $time = \Drupal::time()->getRequestTime();

    if ($this->getOption('duration')) {
      if ($this->getCourseObject()->getFulfillment($account)->getOption('date_started')) {
        $duration_end = $this->getCourseObject()->getFulfillment($account)->getOption('date_started') + ($this->getOption('duration'));
        if ($time > $duration_end) {
          $duration_end_h = \Drupal::service('date.formatter')->format($duration_end, 'long');
          $this->getCourseObject()->setAccessMessage('duration-expired', t('Your enrollment in this activity expired on %date.', array('%date' => $duration_end_h)));
          return FALSE;
        }
      }
    }

    $released = $this->isReleased();

    $expired = $this->isExpired();

    return $released && !$expired;
  }

  function see($account) {
    if (!$this->isReleased() && $this->getOption('release_hidden')) {
      return FALSE;
    }
    if ($this->isExpired() && $this->getOption('expiration_hidden')) {
      return FALSE;
    }
  }

  function view($account) {
    return $this->take($account);
  }

  function optionsForm(&$form, &$form_state) {
    $config = $this->getOptions();

    if (Drupal::moduleHandler()->moduleExists('timeperiod')) {
      $form['duration'] = array(
        '#title' => t('Duration'),
        '#description' => t('Length of time a user can remain in this object before it is closed.'),
        '#type' => 'timeperiod_select',
        '#units' => array(
          '86400' => array('max' => 30, 'step size' => 1),
          '3600' => array('max' => 24, 'step size' => 1),
          '60' => array('max' => 60, 'step size' => 1),
        ),
        '#default_value' => $config['duration'],
      );
    }
    else {
      $form['duration'] = array(
        '#title' => t('Duration'),
        '#description' => t('Length of time in seconds a user can remain in this object before it is closed.'),
        '#type' => 'textfield',
        '#size' => 8,
        '#default_value' => $config['duration'],
      );
    }

    $form['release'] = array(
      '#title' => t('Release date'),
      '#description' => t('When this object can be accessed. If this object is required, users will not be able to proceed until after this date.'),
      '#type' => 'date',
      '#default_value' => $config['release'],
    );

    $form['expiration'] = array(
      '#title' => t('Expiration date'),
      '#description' => t('When this object will close. If this object is required, users will not be able to proceed to the next activity after this date.'),
      '#type' => 'date',
      '#default_value' => $config['expiration'],
    );

    $form['release_hidden'] = array(
      '#title' => t('Hide until release date'),
      '#type' => 'checkbox',
      '#description' => t('Hide the object until the release date. For example, an evaluation after a live event.'),
      '#default_value' => $config['release_hidden'],
    );

    $form['expiration_hidden'] = array(
      '#title' => t('Hide after expiration date'),
      '#type' => 'checkbox',
      '#description' => t('Hide the object after the expiration. For example, an optional pre-test that expires.'),
      '#default_value' => $config['expiration_hidden'],
    );

    return $form;
  }

  function isReleased() {
    $release_date = strtotime($this->getOption('release'));
    if (\Drupal::time()->getRequestTime() <= $release_date) {
      $release_date_formatted = \Drupal::service('date.formatter')->format($release_date, 'long');
      $this->getCourseObject()->setAccessMessage('not-open', t('%title will be available on %release.', array('%title' => $this->getCourseObject()->getTitle(), '%release' => $release_date_formatted)));
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  function isExpired() {
    $expiration_date = strtotime($this->getOption('expiration'));
    if ($this->getOption('expiration') && \Drupal::time()->getRequestTime() > $expiration_date) {
      $expiration_date_formatted = \Drupal::service('date.formatter')->format($expiration_date, 'long');
      $this->getCourseObject()->setAccessMessage('closed', t('%title closed on %expiration.', array('%title' => $this->getCourseObject()->getTitle(), '%expiration' => $expiration_date_formatted)));
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
