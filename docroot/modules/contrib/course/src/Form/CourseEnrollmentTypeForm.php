<?php

namespace Drupal\course\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

class CourseEnrollmentTypeForm extends BundleEntityFormBase {

  /**
   * @{inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $enrollment_type = $this->entity;

    $form['label'] = [
      '#title' => t('Label'),
      '#type' => 'textfield',
      '#default_value' => $enrollment_type->label(),
      '#description' => t('The admin-facing name.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $enrollment_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\course\Entity\CourseEnrollmentType::load',
        'source' => ['label'],
      ],
    ];

    return $form;
  }

}
