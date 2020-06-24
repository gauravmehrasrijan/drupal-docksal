<?php

namespace Drupal\course\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\course\Annotation\CourseOutline;
use Traversable;

/**
 * Provides the Course object access plugin manager.
 */
class CourseOutlinePluginManager extends DefaultPluginManager {

  /**
   * Constructs a new CourseObjectAccessManager object.
   *
   * @param Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/course', $namespaces, $module_handler, CourseOutlineInterface::class, CourseOutline::class);

    $this->alterInfo('course_outline_info');
    $this->setCacheBackend($cache_backend, 'course_outline_plugins');
  }

}
