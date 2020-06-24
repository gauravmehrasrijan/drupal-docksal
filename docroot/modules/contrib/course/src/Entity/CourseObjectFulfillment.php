<?php

namespace Drupal\course\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\course\Helper\CourseHandler;
use Drupal\course\Object\CourseObjectNodeFulfillment;
use Drupal\user\Entity\User;
use const REQUEST_TIME;

/**
 * Parent class for course object fulfillment. Unlike Course objects, this is
 * not abstract and can be used when the fulfillment requirements are simple.
 *
 * @ContentEntityType(
 *   id = "course_object_fulfillment",
 *   label = @Translation("Course object fulfillment"),
 *   label_collection = @Translation("Course object fulfillments"),
 *   label_singular = @Translation("Course object fulfillment"),
 *   label_plural = @Translation("Course object fulfillments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course object fulfillment",
 *     plural = "@count course object fulfillments",
 *   ),
 *   admin_permission = "administer course",
 *   permission_granularity = "bundle",
 *   base_table = "course_object_fulfillment",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "cofid",
 *     "uid" = "uid",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\course\Storage\CourseObjectFulfillmentStorage",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 * )
 */
class CourseObjectFulfillment extends CourseHandler {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['coid'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'course_object')
      ->setLabel(t('Course object'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'user')
      ->setLabel(t('User'));

    $fields['complete'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Complete'));

    $fields['grade_result'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Grade result'));

    $fields['date_started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Date started'));

    $fields['date_completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Date completed'));

    $fields['info'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Info'))
      ->setDescription('Extra fulfillment data.');

    $fields['instance'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Instance ID'))
      ->setDescription('An ID used to identify a remote activity fulfillment.');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel('Changed');

    return $fields;
  }

  /**
   * Is this fulfillment complete?
   *
   * @return bool
   */
  function isComplete() {
    return (bool) $this->getOption('complete');
  }

  /**
   * Set this fulfillment complete.
   *
   * @param bool $complete
   *   Set to 0 to un-complete, 1 or omit to complete.
   *
   * @return CourseObjectFulfillment
   */
  function setComplete($complete = 1) {
    if (!$this->getOption('date_completed')) {
      $this->setOption('date_completed', REQUEST_TIME);
    }

    return $this->setOption('complete', $complete);
  }

  /**
   * Set this fulfillment's grade.
   *
   * @param float $grade
   *
   * @return CourseObjectFulfillment
   */
  function setGrade($grade) {
    return $this->setOption('grade_result', $grade);
  }

  /**
   * Get this fulfillment's grade.
   *
   * @return float
   *   A float value of the user's grade for this fulfillment.
   */
  function getGrade() {
    return $this->getOption('grade_result');
  }

  /**
   * Get this fulfillment's course object.
   *
   * @return CourseObject
   */
  function getCourseObject() {
    return CourseObject::load($this->get('coid')->getString());
  }

  /**
   * Track course after saving fulfillment.
   */
  public function save() {
    // Make sure the user is enrolled first.
    if ($this->getCourseObject()->getCourse()->getTracker($this->getUser())) {
      parent::save();
      // Re-evaluate requirements.
      $account = $this->getUser();
      $this->getCourseObject()->getCourse()->getTracker($account)->track();
      return $this;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get this fulfillment's user.
   *
   * @return AccountInterface
   */
  public function getUser() {
    return User::load($this->get('uid')->getString());
  }

  /**
   * Allow arbitrary data to be stored on the fulfillment, without explicitly
   * defining optionsDefinition() in a custom class.
   *
   * It is suggested that modules provide their own fulfillment classes and
   * specify the valid extra options through their own optionsDefinition(). See
   * CourseObjectWebformFulfillment for an example of this.
   */
  function optionsDefinition() {
    return [];
  }

  /**
   * Get the instance ID. This could be the external component ID, a Node ID...
   *
   * @return string
   */
  function getInstanceId() {
    return $this->getOption('instance');
  }

  /**
   * Grant access to the external course object.
   *
   * For example, adding a user to an access control list.
   *
   * @see CourseObjectNodeFulfillment::grant()
   */
  function grant() {

  }

  /**
   * Revoke access to the external course object.
   *
   * For example, removing a user to an access control list.
   *
   * @see CourseObjectNodeFulfillment::revoke()
   */
  function revoke() {

  }

  /**
   * Do any sort of cleanup that the fulfillment needs. Example: deleting quiz
   * results, webform submissions, etc.
   */
  function delete() {

  }

  /**
   * Map this object base to the base entity class.
   */
  public function getEntityType() {
    $entityType = parent::getEntityType();
    $class = get_class($this);
    $entityType->set('originalClass', $class);
    return $entityType;
  }

  /**
   * Reset object access cache.
   *
   * {@inheritdoc}
   */
  public function postSave(\Drupal\Core\Entity\EntityStorageInterface $storage, $update = TRUE) {
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
  }

}
