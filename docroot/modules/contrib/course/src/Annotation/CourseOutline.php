<?php

namespace Drupal\course\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Course outline annotation object.
 *
 * @see \Drupal\course\Plugin\Course\Outline\CourseOutline
 * @see plugin_api
 *
 * @Annotation
 */
class CourseOutline extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
