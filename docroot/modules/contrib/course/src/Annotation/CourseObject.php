<?php

namespace Drupal\course\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a Course object item annotation object.
 *
 * @see \Drupal\course\Plugin\Course\Object\CourseObjectManager
 * @see plugin_api
 *
 * @Annotation
 */
class CourseObject extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
