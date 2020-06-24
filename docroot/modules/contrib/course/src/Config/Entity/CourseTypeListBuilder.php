<?php

namespace Drupal\course\Config\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;

/**
 * Defines the list builder for profile types.
 */
class CourseTypeListBuilder extends ConfigEntityListBuilder {

  public function render() {
    $build = parent::render();
    $build['table']['#caption'] = t('Course types can be used to differentiate types of courses. e.g. module base activities, tree activities, external courses, etc.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['type'] = $this->t('Course type');
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
