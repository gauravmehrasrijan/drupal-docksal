<?php

namespace Drupal\course\Plugin\Action;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use function count;
use function course_get_course;
use function format_date;

/**
 * Action description.
 *
 * @todo not yet working
 *
 * @Action(
 *   id = "course_edit_enrollment_action",
 *   label = @Translation("Edit enrollment"),
 *   type = ""
 * )
 */
class CourseEnrollmentEditAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $node = \Drupal\node\Entity\Node::load($enrollment->cid);
    $account = Drupal\user\Entity\User::load($enrollment->uid);
    if (!$course_enrollment = course_enrollment_load($node, $account)) {
      $course_enrollment->cid = $node->id();
      $course_enrollment->uid = $account->uid;
    }

    // Update enrollment status.
    if ($context['status'] != '') {
      $enrollment->status = $context['status'];
    }

    // Update enrollment duration.
    if ($context['enroll_end']) {
      // Parse date from popup/plain text.
      if ($unixtime = strtotime($context['enroll_end'])) {
        $enrollment->enroll_end = $unixtime;
      }
    }

    // Update completion.
    if ($context['complete'] != '') {
      $course_enrollment->complete = $context['complete'];
    }

    // Update date completed.
    if ($context['date_completed'] != '') {
      if ($unixtime = strtotime($context['date_completed'])) {
        $course_enrollment->date_completed = $unixtime;
      }
    }

    // Update start date
    if ($context['timestamp']) {
      if ($unixtime = strtotime($context['timestamp'])) {
        $enrollment->timestamp = $unixtime;
      }
    }

    $course = course_get_course($node);
    foreach ($course->getObjects() as $key => $courseObject) {
      $coid = $courseObject->getId();
      $fulfillment = $courseObject->getFulfillment($account);
      if ($context['course_objects'][$coid] !== '') {
        // There was a change

        if ($context['course_objects'][$coid] == 1) {
          // Completed
          $fulfillment->setOption('message', "Fulfillment completed via bulk action.");
          $fulfillment->setComplete($context['course_objects'][$coid]);
        }

        if ($context['course_objects'][$coid] == -1) {
          // Delete attempt
          $fulfillment->delete();
        }

        if ($context['course_objects'][$coid] == 0) {
          // Fail user
          $fulfillment->setOption('message', "Fulfillment failed via bulk action.");
          $fulfillment->setComplete(FALSE);
          $fulfillment->setGrade(0);
        }

        $fulfillment->save();
      }
    }

    course_enrollment_save($enrollment);

    \Drupal::messenger()->addStatus(t('Updated enrollment for %user', array('%user' => $account->name)));
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'user') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = array();

    // Load the first enrollment.
    $selection = reset($context['selection']);
    $check_enrollment = Drupal\course\Entity\CourseEnrollment::load($selection);
    $node = \Drupal\node\Entity\Node::load($check_enrollment->nid);

    // Check if this action is being performed on a single user, and set the
    // account accordingly.
    $account = NULL;
    $course_enrollment = NULL;
    if (count($context['selection']) === 1) {
      // Only one user and course, so let's prefill values.
      $enrollment = $check_enrollment;
      $account = Drupal\user\Entity\User::load($enrollment->uid);
      $course_enrollment = course_enrollment_load($node, $account);
    }

    $form['timestamp'] = array(
      '#title' => t('Set started date to'),
      '#type' => Drupal::moduleHandler()->moduleExists('date_popup') ? 'date_popup' : 'date_text',
      '#date_format' => 'Y-m-d H:i',
      '#description' => t('The date the user started the course.'),
      '#default_value' => !empty($enrollment->timestamp) ? format_date($enrollment->timestamp, 'custom', 'Y-m-d H:i') : NULL,
    );

    $form['enroll_end'] = array(
      '#title' => t('Extend course enrollment until'),
      '#type' => Drupal::moduleHandler()->moduleExists('date_popup') ? 'date_popup' : 'date_text',
      '#date_format' => 'Y-m-d H:i',
      '#description' => t('The date when the user will not be able to access the course.'),
      '#default_value' => !empty($enrollment->enroll_end) ? format_date($enrollment->enroll_end, 'custom', 'Y-m-d H:i') : NULL,
    );

    $form['status'] = array(
      '#title' => t('Set enrollment status to'),
      '#type' => 'select',
      '#options' => array(
        '' => '',
        1 => 'Active',
        0 => 'Inactive',
      ),
      '#default_value' => !empty($enrollment->status) ? $enrollment->status : NULL,
      '#description' => t('Setting an enrollment to "inactive" will prevent a user from accessing the course.'),
    );

    $form['complete'] = array(
      '#title' => t('Set completion status to'),
      '#type' => 'select',
      '#options' => array(
        '' => '',
        1 => t('Complete'),
        0 => t('Incomplete'),
      ),
      '#description' => t("This will change a user's course completion. Set this to incomplete to re-evaluate all requirements. Courses will never be automatically un-completed once they have been marked completed."),
      '#default_value' => !empty($course_enrollment->complete) ? $course_enrollment->complete : NULL,
    );

    $form['date_completed'] = array(
      '#title' => t('Set completion date to'),
      '#type' => Drupal::moduleHandler()->moduleExists('date_popup') ? 'date_popup' : 'date_text',
      '#date_format' => 'Y-m-d H:i',
      '#description' => t('The date of completion.'),
      '#default_value' => !empty($course_enrollment->date_completed) ? format_date($course_enrollment->date_completed, 'custom', 'Y-m-d H:i') : NULL,
    );

    if (isset($node)) {
      // Get course objects, with or without a single user account information.
      $course = course_get_course($node);
      $objects = $course->getObjects();

      // Build a list of a single user's fulfillments.
      $fulfillments = NULL;
      if ($account) {
        $fulfillments = array();
        foreach ($objects as $courseObject) {
          $fulfillments[$courseObject->getId()] = $courseObject->getFulfillment($account);
        }
      }

      $form['course_objects'] = array(
        '#title' => t('Set completion status'),
        '#description' => t('Set the status of a course object to be applied to selected users.'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
        '#prefix' => '<span id="course-objects-wrapper">',
        '#suffix' => '</span>',
      );

      foreach ($objects as $courseObject) {
        $form['course_objects'][$courseObject->getId()] = array(
          '#type' => 'select',
          '#title' => check_plain($courseObject->getTitle()),
          '#options' => array(
            '' => '- no change - ',
            1 => t('Complete'),
            -1 => t('Incomplete'),
            0 => t('Failed'),
          ),
          '#default_value' => $fulfillments ? $fulfillments[$courseObject->getId()]->isComplete() : NULL,
        );
      }
    }
  }

}
