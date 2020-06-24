<?php

namespace Drupal\course_content\Plugin\course\CourseObject;

use Drupal\course_content\Course\Object\CourseObjectNode;
use Drupal\node\Entity\NodeType;

/**
 * @CourseObject(
 *   id = "content",
 *   label = "Course content",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_content\Course\Object\CourseObjectNodeFulfillment"
 *   }
 * )
 */
class CourseObjectContent extends CourseObjectNode {

  function getNodeTypes() {
    $content_types = [];
    $types = NodeType::loadMultiple();
    foreach ($types as $name => $type) {
      if ($type->getThirdPartySetting('course_content', 'use')) {
        $content_types[] = $name;
      }
    }
    return $content_types;
  }

  /**
   * If course object is saved without configuration, make sure we have a
   * default node type.
   */
  function optionsDefinition() {
    $options = parent::optionsDefinition();
    $options['node_type'] = $this->getNodeTypes()[0];
    return $options;
  }

}
