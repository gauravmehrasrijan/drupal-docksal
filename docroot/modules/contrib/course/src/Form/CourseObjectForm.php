<?php

namespace Drupal\course\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use function course_get_course_object_by_id;

class CourseObjectForm extends FormBase {

  /**
   * @{inheritdoc}
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param type $course
   * @param type $course_object
   */
  public function buildForm(array $form, FormStateInterface $form_state, $course = NULL, $course_object = NULL) {
    $form = array();

    // Load object from session.
    if (!$courseObject = _course_get_course_object_by_uniqid($course_object)) {
      $courseObject = \Drupal\course\Entity\CourseObject::load($course_object);
    }

    $form[$courseObject->getComponent()] = array(
      '#title' => $courseObject->getComponentName(),
      '#type' => 'details',
      '#group' => 'course_tabs',
      '#description' => t('Configuration for @name course objects.', array('@name' => $courseObject->getComponentName())),
      '#weight' => 2,
    );

    $courseObject->optionsForm($form, $form_state);

    $form_display = EntityFormDisplay::collectRenderDisplay($courseObject, 'default');
    $form_display->buildForm($courseObject, $form, $form_state);

    foreach (Element::children($form) as $key) {
      $element = $form[$key];
      if (!empty($element['#type']) && $element['#type'] == 'container') {
        $form['title'][$key] = $element;
        unset($form[$key]);
      }
    }

    $fieldset_key = $courseObject->getComponent();


    foreach (Element::children($form) as $key) {
      $element = $form[$key];
      // @todo I want to catch all object-provided fields and group them into a
      // fieldset. I should probably do this with an OO design change so that we
      // know where the fields are coming from. Consider adding a
      // CourseObject::objectOptionsForm which will separate object-specific
      // behavior from Course-specific behavior.
      if (!empty($element['#type']) && !in_array($element['#type'], array('', 'hidden', 'details', 'submit', 'button', 'fieldset', 'vertical_tabs', 'value'))) {
        $form[$fieldset_key][$key] = $element;
        unset($form[$key]);
      }
    }

    return $form;
  }

  public function getFormId() {
    return 'course_object_options_form';
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
