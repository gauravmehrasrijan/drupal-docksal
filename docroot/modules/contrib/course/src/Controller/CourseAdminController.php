<?php

namespace Drupal\course\Controller;

use Drupal\system\Controller\SystemController;

class CourseAdminController extends SystemController {

  /**
   * {@inheritdoc}
   */
  public function overview($link_id = 'course.admin') {
    $build['blocks'] = parent::overview($link_id);
    return $build;
  }

}
