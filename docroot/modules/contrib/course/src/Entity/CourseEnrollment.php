<?php

namespace Drupal\course\Entity;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use const REQUEST_TIME;
use function count;
use function entity_delete_multiple;

/**
 * Defines the profile entity class.
 *
 * @ContentEntityType(
 *   id = "course_enrollment",
 *   label = @Translation("Course enrollment"),
 *   label_collection = @Translation("Course enrollments"),
 *   label_singular = @Translation("course_enrollment"),
 *   label_plural = @Translation("course_enrollments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count enrollment",
 *     plural = "@count enrollments",
 *   ),
 *   bundle_label = @Translation("Course enrollment type"),
 *   bundle_entity_type = "course_enrollment_type",
 *   admin_permission = "administer course",
 *   permission_granularity = "bundle",
 *   base_table = "course_enrollment",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.course_enrollment_type.edit_form",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "eid",
 *     "bundle" = "type",
 *     "uid" = "uid",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *      }
 *   }
 * )
 */
class CourseEnrollment extends ContentEntityBase {

  /**
   * Use the Course's configured enrollment type.
   *
   * {@inheritdoc}
   */
  public static function create(array $values = array()) {
    $course = Course::load($values['cid']);
    $values['type'] = $course->get('enrollment_type')->getString();
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $course_access = $this->getCourse()->access('update', $account);
    $admin_course = $account->hasPermission('administer course');
    $admin_enrollments = $account->hasPermission('administer course enrollments');

    return AccessResult::allowedIf($course_access || $admin_course || $admin_enrollments);
  }

  /**
   * Enrollment entity label callback.
   */
  function defaultLabel() {
    $node = Node::load($this->nid);
    $account = User::load($this->uid);
    return t("@username's enrollment in @title", array('@username' => format_username($account), '@title' => $node->title));
  }

  /**
   * If a duration is set on the course, apply it to this enrollment.
   *
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    $course = $this->getCourse();
    if ($this->get('enroll_end')->isEmpty() && !$course->get('duration')->isEmpty()) {
      // Set enrollment end to now + the duration of the course.
      $this->set('enroll_end', REQUEST_TIME + $course->get('duration')->getString());
    }

    $this->evaluate();

    parent::preSave($storage);
  }

  /**
   * @kludge Reset the static lookup cache.
   */
  public function save() {
    $watchdog_variables = array(
      '@uid' => $this->getUser()->id(),
      '@cid' => $this->getCourse()->id(),
    );

    $ret = parent::save();

    if ($this->isNew()) {
      Drupal::logger('course_enroll')->notice('Enrolled user @uid into @cid.', $watchdog_variables);
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['cid'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'course')
      ->setRequired(TRUE)
      ->setLabel(t('Course'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setLabel(t('User'));

    $fields['enrollmenttype'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t('The creation source of the enrollment.'));

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(1)
      ->setLabel(t('Status'));

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Start'));

    $fields['enroll_end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('End'));

    $fields['complete'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Complete'));

    $fields['date_completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Date completed'));

    $fields['grade_result'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Grade result'));

    $fields['section'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Section'));

    $fields['section_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Section name'));

    $fields['coid'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'course_object')
      ->setLabel(t('Course object ID'));

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setRevisionable(TRUE)
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel('Changed');

    return $fields;
  }

  /**
   * Get the Course for this enrollment.
   *
   * @return Course
   */
  function getCourse() {
    return Course::load($this->get('cid')->getString());
  }

  /**
   * Get the User for this enrollment.
   *
   * @return AccountInterface
   */
  function getUser() {
    return User::load($this->get('uid')->getString());
  }

  public function track() {
    $this->evaluate();
    $this->save();
  }

  /**
   * Track the course (scan required objects, update progress, completion, etc).
   */
  public function evaluate() {
    $required = 0;
    $required_complete = 0;
    $prev = NULL;
    $account = $this->getUser();
    $grades = [];
    foreach ($this->getCourse()->getObjects() as $courseObject) {
      if (!$courseObject->get('enabled')->value) {
        continue;
      }

      if (!$prev) {
        $this->set('section_name', $courseObject->getTitle());
        $this->set('coid', $courseObject->getId());
      }

      // Count required objects.
      $required += (int) $courseObject->get('required')->getString();

      // Count completed required objects.
      $required_complete += ($courseObject->get('required')->getString() && $courseObject->getFulfillment($account)->isComplete());

      // Log last grade.
      if ($courseObject->isGraded() && $courseObject->getOption('grade_include')) {
        $grades[$courseObject->id()] = $courseObject->getFulfillment($account)->getOption('grade_result');
      }

      if (!$courseObject->getFulfillment($account)->isComplete() && $prev && $prev->getFulfillment($account)->isComplete()) {
        $this->set('section_name', $courseObject->getTitle());
        $this->set('coid', $courseObject->getId());
      }

      $prev = clone $courseObject;
    }

    if (!empty($grades)) {
      $this->set('grade_result', array_sum($grades) / count($grades));
    }

    if ($required_complete >= $required) {
      // Course requirements have been met.
      $this->set('section', 'complete');
      $this->set('section_name', 'Complete');
      $this->set('complete', 1);
      if ($this->get('date_completed')->isEmpty()) {
        $this->set('date_completed', REQUEST_TIME);
      }
    }
  }

  /**
   * Clean up fulfillments after deleting an enrollment.s
   *
   * {@inheritdoc}
   */
  function delete() {
    parent::delete();

    // Find all course objects in this course and delete the fulfillments.
    $coids = array();
    $result = Drupal::database()->query("SELECT coid FROM {course_object} WHERE cid = :cid", array(':cid' => $this->getCourse()->id()));
    while ($row = $result->fetch()) {
      $coids[] = $row->coid;
    }

    if (count($coids)) {
      $sql = "SELECT cofid FROM {course_object_fulfillment} WHERE coid IN (:coids[]) AND uid = :uid";
      $cofid = Drupal::database()->query($sql, array(':coids[]' => $coids, ':uid' => $this->getUser()->id()))->fetchAllKeyed(0, 0);
      entity_delete_multiple('course_object_fulfillment', $cofid);
    }
  }

  /**
   * Check if the user has completed this course.
   *
   * @return bool
   */
  function isComplete() {
    return (bool) $this->get('complete')->getString();
  }

  /**
   * Reset enrollment access cache.
   *
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    \Drupal::entityTypeManager()->getAccessControlHandler('course')->resetCache();
    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
  }

}
