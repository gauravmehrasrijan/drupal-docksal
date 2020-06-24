<?php

namespace Drupal\course\Entity;

use Drupal;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\course\Helper\CourseHandler;
use Drupal\course\Plugin\CourseObjectAccessPluginBase;
use stdClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use function course_editing_start;
use function course_get_handlers;
use function course_iframe;
use function views_embed_view;

/**
 * Parent abstract base class of all course objects.
 *
 * Represents a course object in the database.
 *
 * Also holds a fulfillment record if a user is given.
 *
 * @ContentEntityType(
 *   id = "course_object",
 *   label = @Translation("Course object"),
 *   label_collection = @Translation("Course objects"),
 *   label_singular = @Translation("Course object"),
 *   label_plural = @Translation("Course objects"),
 *   label_count = @PluralTranslation(
 *     singular = "@count course object",
 *     plural = "@count course object fulfillments",
 *   ),
 *   admin_permission = "administer course",
 *   permission_granularity = "bundle",
 *   bundle_label = @Translation("Course object type"),
 *   bundle_entity_type = "course_object_type",
 *   base_table = "course_object",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.course_object_type.edit_form",
 *   show_revision_ui = TRUE,
 *   revision_table = "course_object_revision",
 *   revision_data_table = "course_object_field_revision",
 *   entity_keys = {
 *     "id" = "coid",
 *     "uid" = "uid",
 *     "bundle" = "object_type",
 *     "label" = "title"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   handlers =  {
 *     "access" = "Drupal\course\Access\CourseObjectAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "storage" = "Drupal\course\Storage\CourseObjectStorage",
 *     "view_builder" = "Drupal\course\View\CourseObjectViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   }
 * )
 */
abstract class CourseObject extends CourseHandler {

  protected $accessMessages = array();

  /**
   * Override navigation links.
   *
   * @return array
   *   An array of navigation links. Keyed values will override matching items
   *   in Course::getNavigation().
   */
  public function overrideNavigation() {
    return array();
  }

  /**
   * Specify whether fulfillment uses asynchronous polling.
   *
   * @return bool
   *   Whether this object uses polling. Defaults to FALSE.
   */
  public function hasPolling() {
    return FALSE;
  }

  /**
   * Overrides a course outline list item.
   *
   * @param array $item
   *   A course outline list item. The structure mirrors an array element from
   *   the $items param from theme_item_list().
   */
  public function overrideOutlineListItem(&$item) {

  }

  public function isActive() {
    return $this->getCourse()->current()->getId() == $this->getId();
  }

  /**
   * Define configuration elements and their defaults.
   *
   * Extended classes should call parent::optionsDefinition first to get the
   * parent's configuration.
   */
  public function optionsDefinition() {
    $defaults = parent::optionsDefinition();

    $defaults += array(
      'uniqid' => NULL,
      'nid' => NULL,
      'title' => NULL,
      'enabled' => 1,
      'hidden' => 0,
      'required' => 1,
      'skippable' => 0,
      'delete' => 0,
      'delete_instance' => 0,
      'grade_include' => 0,
      'instance' => NULL,
      'plugins' => array(),
      'duration' => NULL,
      'skippable' => 0,
      'use_node_title' => 1,
    );

    return $defaults;
  }

  /**
   * Default options form for all course objects.
   */
  public function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);

    $config = $this->getOptions();

    $form['header']['#markup'] = t("<h2>Settings for %t</h2>", array('%t' => $this->getTitle()));

    $form['uniqid'] = array(
      '#type' => 'value',
      '#value' => $this->getId(),
    );


    $form['course_tabs']['#type'] = 'vertical_tabs';
    $form['course_tabs']['#default_tab'] = 'edit-title';

    $form['title'] = array(
      '#title' => t('Title & description'),
      '#type' => 'details',
      '#group' => 'course_tabs',
      '#weight' => 0,
    );

    $form['outline'] = array(
      '#type' => 'details',
      '#title' => t('Settings'),
      '#group' => 'course_tabs',
      '#weight' => 1,
    );

    $form['plugins']['access'] = array(
      '#type' => 'details',
      '#title' => 'Access',
      '#group' => 'course_tabs',
      '#weight' => 4,
    );

    $form['delete'] = array(
      '#type' => 'details',
      '#title' => 'Delete',
      '#group' => 'course_tabs',
      '#weight' => 5,
    );

    $form['title']['title'] = array(
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#description' => t('The title of this course object as it will appear to users in the course outline.'),
      '#size' => 100,
      '#default_value' => $config['title'],
      '#group' => 'description',
      '#required' => TRUE,
    );

    $form['outline']['enabled'] = array(
      '#title' => t('Enabled'),
      '#type' => 'checkbox',
      '#description' => t('Enabled course objects will become part of the course. Uncheck this box if you are not using this course object.'),
      '#default_value' => $config['enabled'],
    );

    $form['outline']['hidden'] = array(
      '#title' => t('Visible in outline'),
      '#type' => 'checkbox',
      '#description' => t('Objects that are not visible will not be seen by the learner. Uncheck this box for course objects that you do not want the learner to see.'),
      '#default_value' => !$config['hidden'],
      '#group' => 'course',
    );

    $form['outline']['required'] = array(
      '#title' => t('Completion required'),
      '#type' => 'checkbox',
      '#description' => t('Users must complete required objects. Uncheck this box if this is an optional course object.'),
      '#default_value' => $config['required'],
    );

    $form['outline']['skippable'] = array(
      '#title' => t('Skippable'),
      '#type' => 'checkbox',
      '#default_value' => $config['skippable'],
      '#states' => array('visible' => array('#edit-required' => array('checked' => TRUE))),
      '#description' => t('Users can proceed past this object but it will still be required for course completion.'),
    );

    // Delete object
    $form['delete']['delete_button'] = array(
      '#value' => t('Delete'),
      '#weight' => 999,
      '#type' => 'submit',
      '#submit' => array(
        array($this, 'setDelete'),
        array($this, 'optionsSubmit'),
      ),
      '#limit_validation_errors' => array(),
    );

    // Only allow deletion of existing instances.
    if (!empty($config['instance'])) {
      $form['delete']['delete_instance'] = array(
        '#title' => t('Also delete the referenced content.'),
        '#type' => 'checkbox',
        '#default_value' => $config['delete_instance'],
        '#stats' => array('visible' => array('#edit-delete' => array('checked' => TRUE))),
        '#group' => 'delete',
      );

      // Check for multiple instances.
      if (Drupal::database()->query("SELECT count(coid) FROM {course_object} WHERE object_type = :object_type AND instance = :instance", array(':object_type' => $config['object_type'], ':instance' => $config['instance']))->fetchField() > 1) {
        $form['delete']['delete_instance']['#description'] = t('<span class="error"><strong>WARNING</strong></span>: multiple course objects link to this instance. Deleting the instance might break the other course objects that use it.');
      }
    }

    if ($this->isGraded()) {
      $form['grading'] = array(
        '#title' => t('Grading'),
        '#type' => 'details',
        '#description' => t('Settings for graded objects.'),
        '#group' => 'course_tabs',
        '#weight' => 2,
      );

      $form['grading']['grade_include'] = array(
        '#title' => t('Include in final course grade'),
        '#description' => t('Include this grade result for calculation of the final course grade.<br/>Currently, only the last grade result per Course will be used.'),
        '#default_value' => $config['grade_include'],
        '#type' => 'checkbox',
      );
    }

    // Bring in access plugin configuration.
    $form['plugins']['#tree'] = TRUE;
    $form['plugins']['access']['#title'] = t('Access');
    $form['plugins']['access']['#description'] = t('By default, all required objects appearing before this object in the course outline must be completed before the user may access this object. Conditional access allows for additional conditions to be applied.');
    $form['plugins']['access']['#type'] = 'details';

    $pluginManager = Drupal::service('plugin.manager.course.object.access');
    $plugins = $pluginManager->getDefinitions();
    foreach ($plugins as $key => $plugin) {
      $form['plugins']['access']['#tree'] = TRUE;
      $form['plugins']['access'][$key] = array(
        '#title' => $plugin['label'],
        '#type' => 'details',
        '#tree' => TRUE,
        '#open' => FALSE,
      );

      // Initialize access class.
      /* @var $courseAccess CourseObjectAccessPluginBase */
      $courseAccess = $pluginManager->createInstance($key);
      $courseAccess->setCourseObject($this);
      $courseAccess->setType($key);

      // Add access plugin form to our form.
      $access_form = $access_form_state = array();
      $form['plugins']['access'][$key] += $courseAccess->optionsForm($access_form, $access_form_state);
    }

    // Update settings
    $form['actions']['update'] = array(
      '#value' => t('Update'),
      '#weight' => 999,
      '#type' => 'submit',
      '#validate' => array(
        array($this, 'optionsValidate'),
      ),
      '#submit' => array(
        array($this, 'optionsSubmit'),
      ),
    );
  }

  /**
   * Mark this object for deletion.
   */
  public function setDelete(&$form, FormStateInterface $form_state) {
    $form_state->setValue('delete', 1);
    if (!empty($form_state->getUserInput()['delete_instance'])) {
      $form_state->setValue('delete_instance', $form_state->getUserInput()['delete_instance']);
    }
  }

  public function optionsValidate(&$form, FormStateInterface $form_state) {
    // Pass validation to plugins.
//    ctools_include('plugins');
//    foreach (ctools_get_plugins('course', 'course_object_access') as $key => $plugin) {
//      $values = & $form_state['values']['plugins']['access'][$key];
//      $class = ctools_plugin_get_class($plugin, 'handler');
//      $instance = new $class($values);
//      $instance->setCourseObject($this);
//      $instance->setType($key);
//      $instance->optionsValidate($form['plugins']['access'][$key], $form_state['values']['plugins']['access'][$key]);
//    }
  }

  /**
   * Save object configs to cache.
   */
  public function optionsSubmit(&$form, FormStateInterface $form_state) {
    $uniqid = $this->getId();
    $course = $form_state->getBuildInfo()['args'][0];
    $cid = $course->id();

    // Start editing session.
    course_editing_start($this->getCourse());

    // Flip 'visible' so it behaves like 'hidden'.
    if ($form_state->getValue('hidden')) {
      $form_state->setValue('hidden', $form_state->getValue('hidden') != 1);
    }

    // Object-specific settings
    $_SESSION['course'][$cid]['editing'][$uniqid] = $form_state->getValues() + $_SESSION['course'][$cid]['editing'][$uniqid];
  }

  /**
   * Get core options summary.
   *
   * @return array
   *   An associative array of summary keys and values.
   */
  public function getOptionsSummary() {
    $summary = parent::getOptionsSummary();

    // Get options.
    $options = $this->getOptions();

    // Enabled.
    if ($options['enabled']) {
      $summary['enabled'] = t('Enabled');
    }
    else {
      $summary['enabled'] = '<span class="warning">' . t('Not enabled') . '</span>';
    }

    // Hidden.
    if (!$options['hidden']) {
      $summary['hidden'] = t('Visible in outline');
    }
    else {
      $summary['hidden'] = '<span class="warning">' . t('Not visible in outline') . '</span>';
    }

    // Required.
    if ($options['required']) {
      $summary['required'] = t('Completion required');
      if ($options['skippable']) {
        $summary['skippable'] = '<span class="warning">' . t('Skippable') . '</span>';
      }
    }
    else {
      $summary['required'] = '<span class="warning">' . t('Completion not required') . '</span>';
    }

    // Instance edit link.
    $editUrl = $this->getEditUrl();
    if (!empty($editUrl)) {
      $text = t('Edit instance');
      $summary['instance'] = Link::fromTextAndUrl($text, $editUrl)->toString();
    }
    elseif ($this->isTemporary()) {
      $summary['instance'] = '<span class="warning">' . t('Save course to edit object') . '</span>';
    }

    // Instance view link.
    $viewUrl = $this->getViewUrl();
    if (!empty($viewUrl)) {
      $text = t('View instance');
      $summary['instance_view'] = Link::fromTextAndUrl($text, $viewUrl)->toString();
    }

    // Required.
    if (!empty($options['delete'])) {
      $dest = Url::fromRoute('course.object.restore', ['course' => $options['cid'], 'course_object' => $this->getId()], ['attributes' => ['class' => 'use-ajax']]);
      $text = t('Object will be removed from outline');
      $restore_text = t('Restore this object to the course outline.');
      if ($options['delete_instance']) {
        $text = t('Object will be removed from outline, and related instance(s) will be deleted');
        $restore_text = t('Restore this object and related instance(s) to the course outline.');
      }
      $restore = Link::fromTextAndUrl(t('Restore'), $dest, $restore_text)->toString();
      $summary['delete'] = '<span class="error">' . $text . '</span>';
      $summary['restore'] = $restore;
    }

    return $summary;
  }

  /**
   * Get all course object implementations of getOptionsSummary().
   *
   * @todo need plugins
   */
  public function renderOptionsSummary() {
    $summary = $this->getOptionsSummary();
    $out = [];
    foreach ($summary as $key => $item) {
      $out[$key]['#markup'] = $item;
    }
    return $out;
  }

  /**
   * Get options, with session options, except weight, having precedence.
   */
  public function getOptions() {
    $options = parent::getOptions();
    $sessionDefaults = array();
    if (isset($options['cid']) && isset($options['coid']) && isset($_SESSION['course'][$options['cid']]['editing'][$options['coid']])) {
      $sessionDefaults += array_filter((array) $_SESSION['course'][$options['cid']]['editing'][$options['coid']], array($this, 'optionFilter'));
      unset($sessionDefaults['weight']);
    }
    return array_merge($options, (array) $sessionDefaults);
  }

  private function optionFilter($a) {
    return !is_null($a);
  }

  /**
   * Take a course object.
   *
   * - Set the session of this course object being taken. This allows for
   *   non-node objects to be tracked.
   * - Delegate the course object take functionality
   *
   * @return mixed
   *   HTML content or a redirect.
   */
  public final function takeObject() {
    $account = Drupal::currentUser();
    $_SESSION['course']['active'] = $this->getCourse()->id();
    $_SESSION['course'][$this->getCourse()->id()]['taking']['active'] = $this->getId();

    // Run access checks.
    if ($this->access('take')) {
      // Grant access to external course object.
      $this->getFulfillment($account)->grant();

      // Record start date.
      $this->getFulfillment($account)->save();
    }
    else {
      // User can't access this object, revoke access.
      $this->getFulfillment($account)->revoke();
      return FALSE;
    }

    // If we're not displaying any content but we want to fire take() anyway, to
    // let the course object know we sent the user.
    $out = $this->take();

    $url = $this->getTakeUrl();
    switch ($this->getTakeType()) {
      case 'iframe':
        return course_iframe($url);
      case 'popup':
        return "will popup $url";
      case 'content':
        return $out;
      case 'redirect':
      default:
        // This URL should have already been url()'d (it might be external).
        return new RedirectResponse($url->toString());
    }
  }

  /**
   * How should this course object be executed?
   *
   * - iframe: display an iframe with getTakeUrl() in it
   * - popup: launch getTakeUrl() in a popup
   * - modal: launch getTakeUrl() in a modal
   * - content: print the value from take() (or do whatever the module wants to
   *   do)
   */
  public function getTakeType() {
    return 'content';
  }

  /**
   * Course object entry point for taking. This method should return a value
   * corresponding to the type set in getTakeType().
   */
  public function take() {
    return t('This should be overridden by the module to return course content.');
  }

  /**
   * Return the URL to the course object router.
   *
   * @return Url
   */
  public function getUrl() {
    return Url::fromRoute('course.object', ['course' => $this->getCourse()->id(), 'course_object' => $this->id()]);
  }

  /**
   * Get the URL to take this course object, if any.
   *
   * Outline handlers or subclasses should use getUrl().
   *
   * @return Url
   */
  protected function getTakeUrl() {

  }

  /**
   * Get the URL to edit this course object, if any.
   *
   * @return Url
   */
  public function getEditUrl() {

  }

  /**
   * Get the URL to view this course object, if any.
   *
   * @return Url
   */
  public function getViewUrl() {

  }

  /**
   * Is this course object required for course completion?
   *
   * @return bool
   */
  public function isRequired() {
    return (bool) $this->getOption('required');
  }

  /**
   * If this course object is required, can be it skipped?
   *
   * @return bool
   */
  public function isSkippable() {
    return (bool) $this->getOption('skippable');
  }

  /**
   * Is this object graded?
   *
   * Returning TRUE here will cause some more configurations to show on the
   * object's form.
   *
   * @return bool
   */
  function isGraded() {
    return FALSE;
  }

  /**
   * Get the user's status in this course object.
   *
   * This is how an object would notify the user why they cannot proceed to the
   * next step from the course outline. For example, if this was a quiz and
   * they failed, this should let them know.
   */
  public function getStatus() {

  }

  /**
   * Get a user's fulfillment for this course object. If the user has not
   * started this course object, a new, unsaved fulfillment will be return.
   *
   * @param stdClass $account
   *   User account to get fulfillment for.
   *
   * @return CourseObjectFulfillment
   */
  public function getFulfillment(AccountInterface $account) {
    $entities = \Drupal::entityTypeManager()->getStorage('course_object_fulfillment')->loadByProperties(['coid' => $this->id(), 'uid' => $account->id()]);
    if ($entities) {
      return reset($entities);
    }
    else {
      return CourseObjectFulfillment::create(array('coid' => $this->id(), 'uid' => $account->id(), 'object_type' => $this->get('object_type')->getString()));
    }
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
   * Set this object's instance ID.
   *
   * @param string $id The external ID of this course object.
   */
  function setInstanceId($id) {
    return $this->setOption('instance', $id);
  }

  /**
   * Set the Course for this CourseObject.
   *
   * @param Course|int $course
   *   A Course or node ID.
   *
   * @return CourseObject
   */
  public function setCourse($course) {
    if (is_numeric($course)) {
      $this->setOption('cid', $course);
    }
    else {
      $this->setOption('cid', $course->id());
    }
    return $this;
  }

  /**
   * Get the Course that contains this CourseObject.
   *
   * @return Course
   */
  function getCourse() {
    return Course::load($this->get('cid')->getString());
  }

  /**
   * Get the object component title for this course object.
   *
   * @return string
   */
  function getComponentName() {
    $handlers = course_get_handlers('object');
    return $handlers[$this->getComponent()]['label'];
  }

  /**
   * Get the object component for this course object.
   *
   * @return string
   */
  function getComponent() {
    return $this->getOption('object_type');
  }

  /**
   * Set the object component for this course object.
   *
   * @param string $component
   *   The object's component.
   *
   * @return CourseObject
   */
  function setComponent($component) {
    return $this->setOption('object_type', $component);
  }

  /**
   * Set the internal course object ID.
   *
   * @param int $coid
   *   ID of the course object.
   */
  function setId($coid) {
    return $this->setOption('coid', $coid);
  }

  /**
   * Creates a course object.
   *
   * For example, this would create the new node and return the node ID if this
   * was a CourseObjectNode.
   *
   * Do not confuse this with save(), which saves the course outline record for
   * tracking.
   *
   * Course objects should call setInstanceId() if this is a course object
   * that creates external resources.
   */
  public function createInstance() {
    //$this->setInstanceId($id);
  }

  /**
   * Objects should implement this method if they need to delete their own
   * content.
   */
  public function deleteInstance() {
    //thing_delete();
  }

  function getTitle() {
    $object_info = course_get_handlers('object');

    // If title is not specified, set title from component.
    if (!$this->getOption('title')) {
      // Get the component name from object info.
      $title = $object_info[$this->getOption('object_type')]['label'];
      $this->setOption('title', $title);
    }

    return $this->getOption('title');
  }

  /**
   * Give the course object a chance do asynchronous polling and set completion
   * on demand.
   *
   * Useful for external objects.
   */
  function poll() {

  }

  /**
   * Let the course object provide its own reports.
   *
   * @return array
   *   An array indexed by report key, containing 'title' which is the menu link
   *   in the course object reports.
   */
  function getReports() {
    return array(
      'default' => array(
        'title' => 'Overview',
      ),
    );
  }

  /**
   * Let the course object provide its own reports.
   *
   * @return array
   *   An array containing:
   *     - title: The title of this report as show on the page
   *     - content: Content to be displayed.
   *     - url: URL to be loaded in an iframe.
   *   Reports should return either 'content' or 'url'.
   */
  function getReport($key) {
    if ($key == 'default') {
      return array(
        'title' => 'Overview',
        'content' => views_embed_view('course_object_report', 'default', $this->getCourse()->id(), $this->getId()),
      );
    }
  }

  function freeze() {

  }

  function thaw($ice) {

  }

  /**
   * Returns an translated error message if this object has issues with cloning.
   *
   * @return mixed
   *   TRUE if cloning is supported, FALSE if cloning is not supported. A string
   *   if the object can clone but with caveats.
   */
  function getCloneAbility() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Apply configuration from session and let objects create their instances
   * before saving the course object.
   */
  public function save() {
    // If there is no title, set it.
    $this->getTitle();

    if ($ice = $this->getOption('freeze')) {
      // Found frozen data. Restore it to this object.
      $this->setInstanceId($this->thaw($ice));
      $this->setOption('freeze', NULL);
    }

    // Pull temporary configuration from session.
    foreach ($this->optionsDefinition() as $key => $default) {
      $value = $this->getOption($key);
      $this->set($key, $value);
    }

    // If there is no instance ID, tell the object to create external content.
    if (!$this->getInstanceId()) {
      $this->createInstance();
    }

    // Set the ID to NULL because this is a temporary course object being
    // created for the first time.
    if (strpos($this->getId(), 'course_object_') !== FALSE) {
      $this->setId(NULL);
    }
    $data = $this->get('data')->getValue();


    // Delegate to parent entity save.
    return parent::save();
  }

  /**
   * Checks the temporary status of a course object.
   */
  function isTemporary() {
    return strpos($this->getId(), 'course_object_') === 0;
  }

  /**
   * Return the number of occurances that can be in a course at the same time.
   * For example, the design of the Certificate module can only have 1 set of
   * mappings per node. The same goes for Course Credit. We may also want a
   * course object that can only be added twice (for example, a before/after
   * comparison).
   *
   * This method is static because we might have to call it without an object
   * being instantiated.
   */
  public static function getMaxOccurences() {
    return FALSE;
  }

  /**
   * Set the context of which course this course object belongs to.
   *
   * The return parameters should be compliant with course_determine_context().
   */
  public static function context() {

  }

  /**
   * Generate URI from course object.
   */
  public function uri() {
    return array(
      'path' => 'node/' . $this->nid . '/object/' . $this->identifier(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['cid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'));

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object'));

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setDisplayConfigurable('view', TRUE)
      ->setLabel(t('Object title'));

    $fields['enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Course ID'));

    $fields['instance'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Instance identifier'))
      ->setDescription('An ID used to identify a remote activity.');

    $fields['required'] = BaseFieldDefinition::create('boolean')
      ->setDisplayConfigurable('view', TRUE)
      ->setLabel(t('Required'));

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'));

    $fields['hidden'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Hidden'));

    $fields['duration'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Duration'));

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
   * Set field in extra data if needed.
   *
   * {@inheritdoc}
   */
  function set($name, $value, $notify = TRUE) {
    if (!in_array($name, array_keys($this->getFieldDefinitions()))) {
      $extra = parent::get('data')->getValue() ?? [];
      $extra[0][$name] = $value;
      return parent::set('data', $extra[0]);
    }
    else {
      return parent::set($name, $value, $notify);
    }
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

  public static function postDelete(Drupal\Core\Entity\EntityStorageInterface $storage, array $entities) {
    $fs = \Drupal::entityTypeManager()->getStorage('course_object_fulfillment');

    $coids = array_keys($entities);
    $fulfillments = $fs->loadByProperties([
      'coid' => $coids,
    ]);
    $fs->delete($fulfillments);

    parent::postDelete($storage, $entities);
  }

  /**
   * Clear static access cache on save.
   *
   * {@inheritdoc}
   */
  public function postSave(Drupal\Core\Entity\EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    \Drupal::entityTypeManager()->getAccessControlHandler('course_object')->resetCache();
  }

}
