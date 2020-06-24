<?php

namespace Drupal\course\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the course object type entity class.
 *
 * @ConfigEntityType(
 *   id = "course_object_type",
 *   label = @Translation("Course object type"),
 *   label_collection = @Translation("Course object types"),
 *   label_singular = @Translation("course object type"),
 *   label_plural = @Translation("course object types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course object type",
 *     plural = "@count course object types",
 *   ),
 *   admin_permission = "administer course",
 *   config_prefix = "object.type",
 *   bundle_of = "course_object",
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
 *     "list_builder" = "Drupal\course\Config\Entity\CourseObjectTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "add" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "edit" = "Drupal\Core\Entity\BundleEntityFormBase",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "add-form" = "/admin/course/course-object-types/add",
 *     "edit-form" = "/admin/course/course-object-types/manage/{course_object_type}",
 *     "delete-form" = "/admin/course/course-object-types/manage/{course_object_type}/delete",
 *     "collection" = "/admin/course/course-object-types"
 *   }
 * )
 */
class CourseObjectType extends ConfigEntityBundleBase {

}
