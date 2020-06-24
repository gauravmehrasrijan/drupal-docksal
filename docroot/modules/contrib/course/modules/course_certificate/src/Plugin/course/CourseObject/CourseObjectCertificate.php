<?php

namespace Drupal\course_certificate\Plugin\course\CourseObject;

use Drupal;
use Drupal\Core\Link;
use Drupal\course\Entity\CourseObject;

/**
 * @CourseObject(
 *   id = "certificate",
 *   label = "Certificate",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course\Entity\CourseObjectFulfillment"
 *   }
 * )
 */
class CourseObjectCertificate extends CourseObject {

  public function take() {
    $account = Drupal::currentUser();
    $opts = ['course' => $this->getCourse()->id(), 'account' => $account->id()];
    // Fulfill immediately.
    $this->getFulfillment($account)->setComplete(1)->save();
    $render[] = Link::createFromRoute(t('Download certificate'), 'certificate.course.user', $opts)->toRenderable();
    return $render;
  }

  public function optionsDefinition() {
    $options = parent::optionsDefinition();
    $options['required'] = 0;
    $options['certificate_node'] = 1;
    return $options;
  }

  public function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);
    $options = $this->getOptions();

    $form['certificate_node'] = array(
      '#title' => t('Use node settings from Certificate'),
      '#type' => 'checkbox',
      '#default_value' => $options['certificate_node'],
      '#description' => t('This will direct the user to node/%course/certificate'),
      '#disabled' => TRUE,
    );
  }

  public function getTakeType() {
    return 'content';
  }

  public function getCloneAbility() {
    return TRUE;
  }

  public static function getMaxOccurences() {
    return 1;
  }

}
