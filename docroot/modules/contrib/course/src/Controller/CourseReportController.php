<?php

namespace Drupal\course\Controller;

use Drupal\Core\Entity\Controller\EntityController;

class CourseReportController extends EntityController {

  /**
   * Returns a render-able array for a test page.
   */
  public function render() {
    $build = [
      '#markup' => $this->t('render test'),
    ];
    return $build;
  }

}
