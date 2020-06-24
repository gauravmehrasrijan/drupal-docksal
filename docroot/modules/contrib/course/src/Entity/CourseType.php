<?php

namespace Drupal\course\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the course type entity class.
 *
 * @ConfigEntityType(
 *   id = "course_type",
 *   label = @Translation("Course type"),
 *   label_collection = @Translation("Course types"),
 *   label_singular = @Translation("course type"),
 *   label_plural = @Translation("course types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course type",
 *     plural = "@count course types",
 *   ),
 *   admin_permission = "administer course",
 *   config_prefix = "type",
 *   bundle_of = "course",
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
 *     "list_builder" = "Drupal\course\Config\Entity\CourseTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\course\Form\CourseTypeForm",
 *       "edit" = "Drupal\course\Form\CourseTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/course/course-types/add",
 *     "edit-form" = "/admin/course/course-types/manage/{course_type}",
 *     "delete-form" = "/admin/course/course-types/manage/{course_type}/delete",
 *     "collection" = "/admin/course/course-types"
 *   }
 * )
 */
class CourseType extends ConfigEntityBundleBase {

}
