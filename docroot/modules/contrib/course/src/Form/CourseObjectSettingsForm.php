<?php

namespace Drupal\course\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CourseObjectSettingsForm.
 */
class CourseObjectSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_object_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('course.settings.object');

    $form['header']['#markup'] = '<p>' . t('Here, you can manage the settings related to course objects.') . '</p>';
    $form['header']['#markup'] .= '<p>' . t('Fields added to the course object entity are included on the course object editing form, and may be used in course object theme hooks.') . '</p>';

    foreach (user_roles() as $role) {
      $roles[$role->id()] = $role->label();
    }

    $form['private_roles'] = array(
      '#title' => 'Default roles allowed access',
      '#description' => t('By default, when a private course object is created, all view access is revoked. Set roles here that will have access to view private course objects without having access.'),
      '#type' => 'checkboxes',
      '#default_value' => $config->get('private_roles'),
      '#options' => $roles,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('course.settings.object')
      ->setData($form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['course.settings.object'];
  }

}
