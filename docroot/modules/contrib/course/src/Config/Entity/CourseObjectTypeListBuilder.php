<?php

namespace Drupal\course\Config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for profile types.
 */
class CourseObjectTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['type'] = $this->t('Course object type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['type'] = $entity->toLink(NULL, 'edit-form');
    return $row + parent::buildRow($entity);
  }

}
