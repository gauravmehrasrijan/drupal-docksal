<?php

namespace Drupal\course\Form;

use Drupal;
use Drupal\Core\Form\FormStateInterface;

class CourseManageForm extends Drupal\Core\Form\FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'course_manage';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Drupal\course\Entity\Course $course = NULL) {
    $collection = 'config.entity.key_store.course';
    $name = 'course:' . $course->id();
    $kv = Drupal::keyValue($collection);
    $course_settings = $kv->get($name);

    $form['status'] = [
      '#title' => $this->t('Enrollments are'),
      '#type' => 'select',
      '#options' => [
        1 => t('Open'),
        0 => t('Closed'),
      ],
      '#default_value' => $course_settings['status'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $course = $form_state->getBuildInfo()['args'][0];

    $collection = 'config.entity.key_store.course';
    $name = 'course:' . $course->id();
    $kv = Drupal::keyValue($collection);
    $course_settings = $kv->get($name);

    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $value) {
      $course_settings[$key] = $value;
    }

    $kv->set($name, $course_settings);
  }

}
