<?php

namespace Drupal\course\Access;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Entity\Course;
use function drupal_static;

/**
 * Access controller for the Course entity.
 */
class CourseAccessControlHandler extends Drupal\entity\UncacheableEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'edit') {
      return AccessResult::allowedIfHasPermission($account, 'administer course');
    }
    else {
      return parent::checkAccess($entity, $operation, $account);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer course');
  }

  /**
   * Handle multiple access denied messages.
   *
   * {@inheritdoc}
   */
  protected function processAccessHookResults(array $access) {
    // @todo sort by weight and allow altering
    return parent::processAccessHookResults($access);
  }

}
