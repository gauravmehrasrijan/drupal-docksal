<?php

namespace Drupal\course\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\course\Entity\CourseObject;
use function course_get_handlers;

class CourseObjectFulfillmentStorage extends SqlContentEntityStorage {

  protected function doCreate(array $values) {
    $available = course_get_handlers('object');
    $ret = $available[$values['object_type']];
    $this->entityClass = $ret['handlers']['fulfillment'] ?? 'Drupal\course\Entity\CourseObjectFulfillment';
    return parent::doCreate($values);
  }

  /**
   * When loading from the database, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records, $load_from_revision = FALSE) {
    $available = course_get_handlers('object');
    $out = [];
    foreach ($records as $id => $record) {
      $co = CourseObject::load($record->coid);
      $ret = $available[$co->get('object_type')->getString()];
      $this->entityClass = $ret['handlers']['fulfillment'] ?? 'Drupal\course\Entity\CourseObjectFulfillment';
      $entities = parent::mapFromStorageRecords([$id => $record], $load_from_revision);
      $out += $entities;
    }
    return $out;
  }

}
