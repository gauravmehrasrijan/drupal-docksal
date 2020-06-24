<?php

namespace Drupal\course\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\course\Entity\CourseEnrollment;
use Drupal\course\Entity\CourseObject;
use Drupal\course\Plugin\CourseOutlinePluginManager;

/**
 * Defines the Course entity class.
 *
 * @ContentEntityType(
 *   id = "course",
 *   label = @Translation("Course"),
 *   label_collection = @Translation("Course"),
 *   label_singular = @Translation("course"),
 *   label_plural = @Translation("courses"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course",
 *     plural = "@count courses",
 *   ),
 *   bundle_label = @Translation("Course type"),
 *   bundle_entity_type = "course_type",
 *   admin_permission = "administer course",
 *   permission_granularity = "bundle",
 *   base_table = "course",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.course_type.edit_form",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "cid",
 *     "bundle" = "outline",
 *     "label" = "title",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\course\View\CourseViewBuilder",
 *     "list_builder" = "Drupal\course\Config\Entity\CourseListBuilder",
 *     "access" = "Drupal\course\Access\CourseAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\course\Form\CourseEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/course/{course}",
 *     "add-page" = "/course/add",
 *     "add-form" = "/course/add/{course_type}",
 *     "delete-form" = "/course/{course}/delete",
 *     "edit-form" = "/course/{course}/edit",
 *     "collection" = "/admin/course/courses",
 *   }
 * )
 */
class Course extends ContentEntityBase {

  // Ordered list of course objects.
  private $courseObjects = array();
  // Course report tracker
  private $tracker;
  // The active course object.
  private $active = NULL;
  // The next course object.
  private $next;
  // The previous course object.
  private $prev;

  /**
   * Get the course tracker for this course/user.
   *
   * @return CourseEnrollment
   */
  public function getTracker(AccountInterface $account) {
    $entities = \Drupal::entityTypeManager()->getStorage('course_enrollment')->loadByProperties(['cid' => $this->id(), 'uid' => $account->id()]);
    if ($entities) {
      return reset($entities);
    }

    return FALSE;
  }

  /**
   * The Drupal path to take this course.
   *
   * @return Url
   */
  public function getUrl() {
    return Url::fromRoute('course.take', ['course' => $this->id()]);
  }

  /**
   * Set the active CourseObject in this Course.
   *
   * @param int $id
   *   A numeric course object ID.
   */
  public function setActive($id = NULL) {
    if (!$id && isset($_SESSION['course'][$this->id()]['taking']['active'])) {
      $id = $_SESSION['course'][$this->id()]['taking']['active'];
    }

    $old = NULL;
    $storeNext = FALSE;
    foreach ($this->getObjects() as $courseObject) {
      if (!$courseObject->getOption('enabled')) {
        // Skip disabled objects.
        continue;
      }

      if ($id == $courseObject->id()) {
        // Active - save old, store next.
        if ($old) {
          $this->prev = $old;
        }

        $storeNext = TRUE;
        $this->active = $courseObject;
      }
      elseif ($storeNext) {
        $this->next = clone $courseObject;
        $storeNext = FALSE;
      }

      $old = clone $courseObject;
    }
  }

  /**
   * Get the active CourseObject.
   *
   * @return CourseObject
   */
  public function getActive() {
    if (!$this->active) {
      $this->setActive();
    }

    return $this->active;
  }

  /**
   * Get the next course object, from the active course object.
   *
   * @return CourseObject
   */
  public function getNext() {
    if (!$this->active) {
      $this->setActive();
    }

    return $this->next;
  }

  /**
   * Get the previous course object, from the active course object.
   *
   * @return CourseObject
   */
  public function getPrev() {
    if (!$this->active) {
      $this->setActive();
    }

    return $this->prev;
  }

  /**
   * Generate navigation links.
   */
  public function getNavigation(AccountInterface $account) {
    // Initialize the active Course.
    $this->setActive();

    $prev = $this->getPrev();
    $next = $this->getNext();

    $links = array();

    if ($prev && $prev->access('take')) {
      $links['prev'] = \Drupal\Core\Link::fromTextAndUrl(t('Previous'), $prev->getUrl());
    }

    $links['back'] = \Drupal\Core\Link::createFromRoute(t('Back to course'), 'entity.course.canonical', ['course' => $this->id()]);

    if ($next && $next->access('take')) {
      $links['next'] = \Drupal\Core\Link::fromTextAndUrl(t('Next'), $next->getUrl());
    }
    elseif (!$next && $this->getTracker($account)->isComplete()) {
      $links['next'] = \Drupal\Core\Link::createFromRoute(t('Next'), 'course.complete', ['course' => $this->id()]);
    }

    // Ask course objects if they want to override the navigation.
    if ($active = $this->getActive()) {
      foreach ($active->overrideNavigation() as $key => $link) {
        $links[$key] = $link;
      }
    }

    return $links;
  }

  /**
   * Get the course objects in this course.
   *
   * @return CourseObject[]
   *   An array of course objects.
   */
  public function getObjects() {
    if (empty($this->courseObjects)) {
      $efq = \Drupal::entityQuery('course_object');
      $result = $efq->condition('cid', $this->id())
        ->sort('weight')
        ->execute();

      if (!empty($result)) {
        $this->courseObjects = \Drupal::entityTypeManager()->getStorage('course_object')
          ->loadMultiple($result);
      }
    }

    return $this->courseObjects;
  }

  function resetCache() {
    // Reset this course's cache.
    $this->courseObjects = array();
    return $this;
  }

  public function getNode() {
    return \Drupal\node\Entity\Node::load($this->nid);
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $config = Drupal::config('course.settings.enrollment');

    // Get course outline plugins.
    /* @var $pluginManager CourseOutlinePluginManager */
    $pluginManager = Drupal::service('plugin.manager.course.outline');
    $outlines = array_column($pluginManager->getDefinitions(), 'label', 'id');

    // Get enrollment bundles.
    $ebundles = array_column(CourseEnrollmentType::loadMultiple(), 'label', 'id');

    $fields['outline'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Outline display'))
      ->setDescription(t('This controls the presentation of the course objects.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setSetting('allowed_values', $outlines);

    $fields['enrollment_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Enrollment type'))
      ->setDescription(t("The enrollment type's fields, if required for enrollment, will be presented to the user before starting the course."))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDefaultValue($config->get('default_enrollment_type'))
      ->setDisplayConfigurable('form', TRUE)
      ->setSetting('allowed_values', $ebundles);


    $fields['credits'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Credits'))
      ->setDescription(t('For more advanced crediting, use the <a href=":link">Course credit</a> module.', array(':link' => Url::fromUri('https://drupal.org/project/course_credit')->toString())))
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // This is a simple credit hours field. If course_credit is enabled it used
    // for storing the maximum credit of any credit instance.
    if (!Drupal::moduleHandler()->moduleExists('course_credit')) {
      $fields['credits']->setDisplayConfigurable('form', FALSE);
    }


    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Duration'))
      ->setDescription(t('Length of time in seconds that a user can remain in the course. Leave blank for unlimited.<br/>For a better experience, install the <a href=":link">Time period</a> module.', array(':link' => Url::fromUri('https://drupal.org/project/timeperiod')->toString())))
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setDisplayConfigurable('form', TRUE);

    if (Drupal::moduleHandler()->moduleExists('timeperiod')) {
      $form['course']['duration']['#type'] = 'timeperiod_select';
      $form['course']['duration']['#units'] = array(
        '86400' => array('max' => 30, 'step size' => 1),
        '3600' => array('max' => 24, 'step size' => 1),
        '60' => array('max' => 60, 'step size' => 1),
      );
      $form['course']['duration']['#description'] = t('Length of time that a user can remain in the course.');
    }


    $fields['external_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('External learning application course ID'))
      ->setDescription('If using an external learning application, the ID of the external course.')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE);

    if (FALSE && arg(2) == 'clone') {
      // @todo not even going to try and make this work right now
      $form['course']['clone_type'] = array(
        '#title' => t('Course object cloning'),
        '#description' => t('"New" will create new instances of all course objects.<br/>"Reference" will link supported content in the old course to the new course.<br/>"Clone" will copy supported course objects, otherwise create new ones.'),
        '#type' => 'radios',
        '#options' => array(
          'clone' => 'Clone',
          'reference' => 'Reference',
          'new' => 'New',
        ),
        '#default_value' => 'clone',
      );
    }

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setRevisionable(TRUE)
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel('Changed');

    $fields['course_date'] = BaseFieldDefinition::create('daterange')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
      ])
      ->setLabel('Course date');

    return $fields;
  }

  /**
   * Check if a user is enrolled.
   *
   * @param AccountInterface $account
   *   Account to check.
   *
   * @return bool
   *   If the user is enrolled.
   */
  function isEnrolled(AccountInterface $account) {
    return (bool) $this->getTracker($account);
  }

  /**
   * Enroll a user in this course.
   *
   * @param AccountInterface $account
   *   The user to enroll.
   * @param array $values
   *   Any other entity values as they would be passed to
   *   CourseEnrollment::create().
   *
   * @return CourseEnrollment
   */
  function enroll(AccountInterface $account, $values = []) {
    if (!$enrollment = $this->getEnrollment($account)) {
      // User is not enrolled yet.
      $enrollment = CourseEnrollment::create([
          'cid' => $this->id(),
          'uid' => $account->id(),
          ] + $values);

      $enrollment->save();
    }

    return $enrollment;
  }

  /**
   * Remove a user from this course.
   *
   * @param AccountInterface $account
   */
  function unEnroll(AccountInterface $account) {
    return $this->getTracker($account)->delete();
  }

  function delete() {
    // Clean up course specific settings and enrollments when a course is
    // deleted.
    \Drupal::database()->delete('course_enrollment')
      ->condition('cid', $this->id())
      ->execute();
    $query = \Drupal::database()->select('course_object', 'co');
    $query->join('course_object_fulfillment', 'cof', 'co.coid = cof.coid');
    $result = $query
      ->fields('co')
      ->condition('co.nid', $this->id())
      ->execute();
    while ($row = $result->fetch()) {
      \Drupal::database()->delete('course_object_fulfillment')
        ->condition('coid', $row->coid)
        ->execute();
    }
    \Drupal::database()->delete('course_object')
      ->condition('cid', $node->id())
      ->execute();

    parent::delete();
  }

  /**
   * Load an enrollment.
   *
   * @todo maybe move to CourseStorage
   *
   * @param AccountInterface $account
   *
   * @return \Drupal\course\Entity\CourseEnrollment
   */
  function getEnrollment(AccountInterface $account) {
    $entities = Drupal::entityTypeManager()->getStorage('course_enrollment')->loadByProperties([
      'cid' => $this->id(),
      'uid' => $account->id(),
    ]);
    if ($entities) {
      return reset($entities);
    }
  }

}
