<?php

namespace Drupal\course\Course\Object;

use Drupal\course\Entity\CourseObject;

class CourseObjectBroken extends CourseObject {

  function take() {
    return t('This course object is misconfigured. Please contact the administrator.');
  }

}
