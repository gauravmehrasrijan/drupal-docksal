<?php

namespace Drupal\course\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Url;
use Drupal\course\Entity\Course;
use Drupal\course\Entity\CourseObject;
use PDO;
use ReflectionClass;
use ReflectionProperty;

class CourseObjectController extends EntityController {

  /**
   * AJAX handler to restore a deleted object.
   *
   * @param Course $course
   *   The course.
   * @param string $coid
   *   Course object ID, which may be a temporary string.
   *
   * @return AjaxResponse
   */
  public function restoreObject(Course $course, $course_object) {
    // Set the session value.
    $_SESSION['course'][$course->id()]['editing'][$course_object]['delete'] = 0;
    $_SESSION['course'][$course->id()]['editing'][$course_object]['delete_instance'] = 0;

    $response = new AjaxResponse;
    $currentURL = Url::fromRoute('course.outline', ['course' => $course->id()]);
    $response->addCommand(new RedirectCommand($currentURL->toString()));
    return $response;
  }

  /**
   * Overrides EntityAPIController::query().
   */
  public function query($ids, $conditions, $revision_id = FALSE) {
    $query = $this->buildQuery($ids, $conditions, $revision_id);
    $result = $query->execute();
    $result->setFetchMode(PDO::FETCH_ASSOC);

    // Build the resulting objects ourselves, since the standard PDO ways of
    // doing that are completely useless.
    $objects = array();
    foreach ($result as $row) {
      $row['is_new'] = FALSE;
      $objects[] = $this->create($row);
    }
    return $objects;
  }

  /**
   * Fork of Entity API's "merge" functionality.
   *
   * Merge the serialized field to the entity object.
   */
  function load($ids = array(), $conditions = array()) {
    $entities = parent::load($ids, $conditions);

    $base_table = $this->entityInfo['base table'];
    $schema = drupal_get_schema($base_table);
    foreach ($schema['fields'] as $field_name => $info) {
      if (!empty($info['serialize'])) {
        $serialized_field = $field_name;
      }
    }

    foreach ($entities as $courseObject) {
      if (isset($courseObject->$serialized_field) && is_array($courseObject->$serialized_field)) {
        $reflect = new ReflectionClass($courseObject);
        foreach ($reflect->getProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED) as $prop) {
          $props[$prop->getName()] = $prop->getName();
        }
        foreach ($courseObject->$serialized_field as $field => $value) {
          if (!isset($props[$field])) {
            $courseObject->setOption($field, $value);
          }
        }
        unset($courseObject->$serialized_field);
      }
    }
    return $entities;
  }

  /**
   * Render the take course object page.
   */
  public function render(CourseObject $course_object) {
    $build = $course_object->takeObject();

    if (is_array($build)) {
      // This is a render array, do not cache the content.
      $build['#cache']['max-age'] = 0;
      return $build;
    }

    return $build;
  }

}
