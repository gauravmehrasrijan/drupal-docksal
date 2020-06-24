<?php

namespace Drupal\course\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class CourseEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * Redirect to the outline form after course creation.
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('course.outline', ['course' => $this->entity->id()]);
  }

}
