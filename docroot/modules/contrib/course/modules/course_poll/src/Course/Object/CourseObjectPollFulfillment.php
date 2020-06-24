<?php

namespace Drupal\course_poll\Course\Object;

use Drupal\course\Object\CourseObjectNode;

class CourseObjectPollFulfillment extends CourseObjectNodeFulfillment {

  /**
   * Remove poll votes for this user.
   */
  function delete() {
    $account = Drupal\user\Entity\User::load($this->uid);

    \Drupal::database()->delete('poll_vote')
      ->condition('nid', $this->getCourseObject()->getInstanceId())
      ->condition('uid', $account->uid)
      ->execute();
    db_update('poll_choice')
      ->expression('chvotes', 'chvotes - 1')
      ->condition('chid', $this->getOption('instance'))
      ->execute();

    parent::delete();
  }

}
