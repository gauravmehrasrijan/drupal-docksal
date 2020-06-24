<?php

namespace Drupal\course\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\course\Entity\CourseEnrollmentType;

/**
 * Course enrollment settings form.
 */
class CourseEnrollmentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_enrollment_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('course.settings.enrollment');
    $types = CourseEnrollmentType::loadMultiple();
    $options = array_column($types, 'label', 'id');

    $form['default_enrollment_type'] = [
      '#title' => t('Default course enrollment type'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $config->get('default_enrollment_type'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('course.settings.enrollment')
      ->setData($form_state->cleanValues()->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['course.settings.enrollment'];
  }

}
