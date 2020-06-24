<?php

namespace Drupal\course\Plugin\Action;

use Drupal;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\course\Entity\Course;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Action description.
 *
 * @Action(
 *   id = "course_add_enrollment_action",
 *   label = @Translation("Enroll user"),
 *   type = ""
 * )
 */
class CourseEnrollmentCreateAction extends ViewsBulkOperationsActionBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /* @var $url Drupal\Core\Url */
    $url = $this->context['redirect_url'];
    $cid = $url->getRouteParameters()['course'];
    $course = Course::load($cid);
    $course->enroll($entity);
    return $this->t('Enrolled user.');
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

}
