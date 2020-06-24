<?php

namespace Drupal\course\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the course enrollment type entity class.
 *
 * @ConfigEntityType(
 *   id = "course_enrollment_type",
 *   label = @Translation("Course enrollment type"),
 *   label_collection = @Translation("Course enrollment types"),
 *   label_singular = @Translation("course enrollment type"),
 *   label_plural = @Translation("course enrollment types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course enrollment type",
 *     plural = "@count course enrollment types",
 *   ),
 *   admin_permission = "administer course",
 *   config_prefix = "enrollment.type",
 *   bundle_of = "course_enrollment",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\course\Config\Entity\CourseEnrollmentTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\course\Form\CourseEnrollmentTypeForm",
 *       "edit" = "Drupal\course\Form\CourseEnrollmentTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/course/enrollment-types/add",
 *     "edit-form" = "/admin/course/enrollment-types/manage/{course_enrollment_type}",
 *     "delete-form" = "/admin/course/enrollment-types/manage/{course_enrollment_type}/delete",
 *     "collection" = "/admin/course/enrollment-types"
 *   }
 * )
 */
class CourseEnrollmentType extends ConfigEntityBundleBase {

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    return AccessResult::allowedIf($account->hasPermission('administer course enrollment types'));
  }

}
