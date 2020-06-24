<?php

namespace Drupal\course\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class CourseObjectStorage extends SqlContentEntityStorage {

  /**
   * When creating a new entity, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    $available = course_get_handlers();
    $ret = $available[$values['object_type']];
    if ($ret['class']) {
      $this->entityClass = $ret['class'];
    }
    else {
      $this->entityClass = 'Drupal\course\Course\Object\CourseObjectBroken';
    }

    return parent::doCreate($values);
  }

  /**
   * When loading from the database, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records, $load_from_revision = FALSE) {
    $available = course_get_handlers();
    $out = [];
    foreach ($records as $id => $record) {
      $ret = $available[$record->object_type];
      if ($ret['class']) {
        $this->entityClass = $ret['class'];
      }
      else {
        $this->entityClass = 'Drupal\course\Course\Object\CourseObjectBroken';
      }
      $entities = parent::mapFromStorageRecords([$id => $record], $load_from_revision);
      $out += $entities;
    }
    return $out;
  }

}
