<?php

namespace Drupal\course\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CourseObjectSettingsForm.
 */
class CourseReportSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'course_report_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['header']['#markup'] = '<p>' . t('Here, you can manage the settings related to course progress and completion.') . '</p>';
    $form['header']['#markup'] .= '<p>' . t('Fields may be added to the course progress tracker entity for future functionality.') . '</p>';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('course.settings.report')
      ->setData($form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['course.settings.report'];
  }

}
