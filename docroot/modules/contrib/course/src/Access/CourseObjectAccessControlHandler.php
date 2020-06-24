<?php

namespace Drupal\course\Access;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Plugin\CourseObjectAccessPluginManager;

/**
 * Access controller for the Course entity.
 */
class CourseObjectAccessControlHandler extends Drupal\entity\UncacheableEntityAccessControlHandler {

  /**
   * Access functionality for course objects.
   *
   * Possible values for $op are 'see', 'view', 'take'.
   *
   * "see" means see it in a course outline. For example, a conditional survey
   * should not be seen in the course outline. A quiz at the end of the course,
   * should show up, but the user must not be able to take it.
   *
   * "view" means view and interact with the object, but nothing would be
   * recorded. For example, accessing a quiz but not being able to submit
   * responses.
   *
   * "take" means interact with the object in a way that records data.
   *
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!in_array($operation, array('see', 'take', 'view'), TRUE)) {
      // This isn't a supported course access operation, so defer to the parent.
      return parent::checkAccess($entity, $operation, $account);
    }


    $access = FALSE;

    if (!$account) {
      $account = Drupal::currentUser();
    }


    if (!$entity->getOption('enabled') || $entity->getOption('hidden')) {
      // Object is disabled or hidden so it should never be visible.
      return AccessResult::forbidden()->resetCacheContexts();
    }

    switch ($operation) {
      case 'see':
        // User can see this object in the outline.
        $access = TRUE;
        break;
      case 'take':
      case 'view':
        if ($account->isAnonymous()) {
          // Not logged in. Should never be accessible.
          return AccessResult::forbidden()->resetCacheContexts();
        }

        // Stock access: check for completion of previous object.
        // Get a copy of the course, so we can run setActive() without changing
        // the global course.
        $course = clone $entity->getCourse();
        $course->setActive($entity->getId());
        $courseObjects = $course->getObjects();

        // Deny object access to non-enrolled users or users who cannot take
        // this course.
        $result = $entity->getCourse()->access('take', $account, TRUE);
        if (!$entity->getCourse()->isEnrolled($account) || $result->isForbidden()) {
          return AccessResult::forbidden()->resetCacheContexts();
        }
        else if (reset($courseObjects)->getId() == $entity->getId()) {
          // User is enrolled. Grant access if first object.
          $access = TRUE;
        }

        if (!$course->getPrev()) {
          // There wasn't a previous object.
          $access = TRUE;
        }
        elseif (!$course->getPrev()->isRequired() || $course->getPrev()->isSkippable()) {
          // Previous step was not required, or was skippable.
          $access = TRUE;

          // But we need to see if at least one required step was completed (or the start of the course).
          $objects = array_reverse($course->getObjects());
          $check = FALSE;
          foreach ($objects as $object) {
            if (!$object->getOption('enabled')) {
              // Do not check this object.
              // Note that hidden objects are still counted when doing
              // fulfillment checks.
              continue;
            }

            if ($check) {
              if ($object->isRequired() && !$object->isSkippable()) {
                // Object is required.
                if (!$object->getFulfillment($account)->isComplete()) {
                  // Found a required object that was not complete.
                  $access = FALSE;
                  break;
                }
                else {
                  // The last required object was completed.
                  $access = TRUE;
                  break;
                }
              }
            }
            if ($object->getId() == $entity->getId()) {
              // We found the object we are trying to check access on.
              // Now we want to go backwards.
              $check = 1;
            }
          }
        }
        elseif ($course->getPrev()->getFulfillment($account)->isComplete()) {
          // If last object was complete, and we are on the current object,
          // grant access.
          $access = TRUE;
        }
    }

    // Plugin access.
    // @todo D8 fix

    /* @var $pluginManager CourseObjectAccessPluginManager */
    $pluginManager = Drupal::service('plugin.manager.course.object.access');
    $plugins = $pluginManager->getDefinitions();
    foreach ($plugins as $key => $plugin) {
      $accessPlugin = $pluginManager->createInstance($key);
      $accessPlugin->setCourseObject($entity);
      $accessPlugin->setType($key);

      // Run access check.
      $ret = $accessPlugin->$operation($account);

      if ($ret === FALSE) {
        // If previous access was granted, revoke it.
        $access = $ret;
      }
    }

    return $access ? AccessResult::allowed() : AccessResult::forbidden()->resetCacheContexts();
  }

}
