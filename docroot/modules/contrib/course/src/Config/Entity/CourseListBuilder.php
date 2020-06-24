<?php

namespace Drupal\course\Config\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines the list builder for course entities.
 */
class CourseListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Course');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = $entity->toLink(NULL, 'edit-form');
    $row['type'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $operations['outline'] = [
      'title' => $this->t('Objects'),
      'weight' => 101,
      'url' => \Drupal\Core\Url::fromRoute('course.outline', ['course' => $entity->id()]),
    ];

    return $operations;
  }

}
