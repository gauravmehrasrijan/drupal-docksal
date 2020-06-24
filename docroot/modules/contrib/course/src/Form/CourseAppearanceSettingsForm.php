<?php

namespace Drupal\course\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CourseSettingsAppearanceForm.
 */
class CourseAppearanceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_appearance_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('course.settings.appearance');

    $form['take_tab_display'] = array(
      '#title' => t('Show a "take course" tab on course nodes'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('take_tab_display'),
    );

    $theme = \Drupal::service('theme_handler')->getDefault();
    $form['disable_regions'] = array(
      '#title' => t('Disable theme regions when taking a course'),
      '#type' => 'checkboxes',
      '#default_value' => $config->get('disable_regions'),
      '#options' => system_region_list($theme),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('course.settings.appearance')
      ->setData($form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['course.settings.appearance'];
  }

}
