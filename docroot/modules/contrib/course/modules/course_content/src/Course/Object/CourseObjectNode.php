<?php

namespace Drupal\course_content\Course\Object;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\course\Entity\CourseObject;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use stdClass;
use function count;
use function course_get_course_object;
use function module_load_include;
use function node_type_get_names;

/**
 * A course object that uses a node as a base.
 */
abstract class CourseObjectNode extends CourseObject {

  /**
   * Course context handler callback.
   */
  public static function context() {
    $route_match = Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.node.canonical') {
      $node = $route_match->getParameter('node');

      $type = NodeType::load($node->bundle());
      if ($type->getThirdPartySetting('course_content', 'use')) {
        if ($courseObject = course_get_course_object('content', $node->id())) {
          return array(
            'object_type' => 'content',
            'instance' => $node->id(),
          );
        }
      }

      // This node might not be in a course, so let's check for related nodes.
      $instances = static::getNodeInstances($node);
      if (!empty($instances)) {
        $node = \Drupal\node\Entity\Node::load($instances[0]);
        // @todo this breaks 'content', need to figure this out
        if ($courseObject = course_get_course_object($node->bundle(), $node->id())) {
          return array(
            'object_type' => $node->bundle(),
            'instance' => $node->id(),
          );
        }
      }
    }
  }

  /**
   * When passed a node, this method should return the "parent" nodes that are
   * contained in a course outline.
   *
   * For example, if the passed node was a question in a quiz, all the quiz node
   * IDs should be returned.
   */
  public static function getNodeInstances($node) {
    return array();
  }

  public function hasNodePrivacySupport() {
    return Drupal::moduleHandler()->moduleExists('content_access') && Drupal::moduleHandler()->moduleExists('acl');
  }

  /**
   * Return a list of valid node types.
   *
   * @return array
   *   An array with node type machine names.
   */
  public abstract function getNodeTypes();

  /**
   * Simple node course object behavior is to just redirect to the node.
   */
  public function getTakeType() {
    return 'redirect';
  }

  /**
   * {@inheritdoc}
   */
  public function getTakeUrl() {
    if ($this->getNode()) {
      return Url::fromRoute('entity.node.canonical', ['node' => $this->getNode()->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEditUrl() {
    if ($this->getNode()) {
      return Url::fromRoute('entity.node.edit_form', ['node' => $this->getNode()->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getViewUrl() {
    if ($this->getNode()) {
      return Url::fromRoute('entity.node.canonical', ['node' => $this->getNode()->id()]);
    }
  }

  /**
   * Create a node and set it as this course object's node.
   *
   * @param stdClass $node
   *   A node to be processed for creation, or none to create a generic node. If
   *   a node is provided, it must have at least a type.
   *
   * {@inheritdoc}
   */
  public function createInstance($node = NULL) {
    if (!$node) {
      $node = Drupal\node\Entity\Node::create(['type' => $this->getOption('node_type')]);
    }

    $node->title = $this->getTitle();
    $node->uid = Drupal::currentUser()->id();
    $node->save();
    $this->setInstanceId($node->id());
  }

  /**
   * Destroy the node instance.
   */
  public function deleteInstance() {
    $node = \Drupal\node\Entity\Node::load($this->getInstanceId());
    if ($node) {
      $node->delete();
    }
  }

  public function optionsDefinition() {
    $defaults = parent::optionsDefinition();

    // @todo this also needs to check if the content exists or not
    $defaults['private'] = $this->hasNodePrivacySupport();

    $options = array_intersect_key(node_type_get_names(), array_combine($this->getNodeTypes(), $this->getNodeTypes()));
    $defaults['node_type'] = key($options);

    $defaults['use_node_title'] = 0;

    $defaults['clone_and_reference'] = 0;

    $defaults['use_existing_node'] = 0;

    return $defaults;
  }

  public function optionsForm(&$form, &$form_state) {
    parent::optionsForm($form, $form_state);

    $form['node'] = array(
      '#type' => 'details',
      '#title' => t('Content'),
      '#description' => ('Settings for course object content.'),
      '#group' => 'course_tabs',
      '#weight' => 2,
    );

    $config = $this->getOptions();

    $types = array_combine($this->getNodeTypes(), $this->getNodeTypes());
    $options = array_intersect_key(node_type_get_names(), $types);

    $form['node']['use_existing_node'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use existing content'),
      '#default_value' => (bool) $this->getOption('use_existing_node'),
      '#weight' => 1,
      '#access' => $this->isTemporary(),
    );

    $form['node']['node_type'] = array(
      '#title' => t('Create node'),
      '#type' => 'select',
      '#options' => $options,
      '#description' => t('Selecting a node type will automatically create this node and link it to this course object.'),
      '#default_value' => $config['node_type'],
      '#states' => array(
        'visible' => array(
          ':input[name="use_existing_node"]' => array('checked' => FALSE),
        ),
      ),
      '#weight' => 2,
      '#access' => $this->isTemporary(),
    );
    if (count($options) > 1) {
      $form['node']['node_type']['#required'] = TRUE;
    }

    $form['node']['instance'] = array(
      '#title' => t('Existing content'),
      '#autocomplete_path' => 'course/autocomplete/node/' . implode(',', $this->getNodeTypes()),
      '#type' => 'textfield',
      '#description' => t('Use existing content instead of creating a new one.'),
      '#default_value' => !empty($this->getInstanceId()) ? $this->getNode()->get('title')->getString() . " [nid: {$this->getInstanceId()}]" : NULL,
      '#maxlength' => 255,
      '#states' => array(
        'visible' => array(
          ':input[name="use_existing_node"]' => array('checked' => TRUE),
        ),
      ),
      '#weight' => 3,
    );

    if (Drupal::moduleHandler()->moduleExists('clone') && !$this->getInstanceId()) {
      $form['node']['clone_and_reference'] = array(
        '#title' => t('Clone and reference'),
        '#type' => 'checkbox',
        '#description' => t('This will clone the selected content first.'),
        '#default_value' => $config['clone_and_reference'],
        '#weight' => 4,
        '#states' => array(
          'visible' => array(
            ':input[name="use_existing_node"]' => array('checked' => TRUE),
          ),
        ),
      );
    }

    $form['node']['use_node_title'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use existing title'),
      '#description' => t("Use the referenced content's title as this course object's title."),
      '#default_value' => $config['use_node_title'],
      '#weight' => 5,
    );

    $form['node']['private'] = array(
      '#title' => t('Private'),
      '#description' => $this->hasNodePrivacySupport() ? t('This content will not be available to users who are not enrolled in this course.') : t('You must enable content_access and acl in order to restrict course content to users who are enrolled in this course.'),
      '#type' => 'checkbox',
      '#default_value' => $config['private'],
      '#disabled' => !($this->hasNodePrivacySupport()),
      '#weight' => 6,
    );

    $nid = $this->getInstanceId();
    if ($nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], array('attributes' => array('target' => '_blank', 'title' => t('Open in new window'))));
      $link = Link::fromTextAndUrl(t("'%title' [node id %nid]", array('%title' => $node->get('title')->getString(), '%nid' => $node->id())), $url)->toString();
      $form['node']['instance']['#description'] = t('Currently set to @link', array('@link' => $link));
    }
  }

  /**
   * Validate the options form. Check the node type.
   */
  public function optionsValidate(&$form, FormStateInterface $form_state) {
    parent::optionsValidate($form, $form_state);
    $nid = $form_state->getValues()['instance'];

    if (empty($nid) && isset($form_state->getValues()['node_type']) && empty($form_state->getValues()['node_type'])) {
      $form_state->setErrorByName('node_type', t('Please select a node type.'));
    }

    $missing_node = !preg_match('/^(?:\s*|(.*) )?\[\s*nid\s*:\s*(\d+)\s*\]$/', $nid);
    if (($form_state->getValues()['use_existing_node'] || !$this->isTemporary()) && $missing_node) {
      $form_state->setErrorByName('instance', t('Please select a node.'));
    }
  }

  public function optionsSubmit(&$form, FormStateInterface $form_state) {
    if ($form_state->getValue('instance')) {
      $nid = $form_state->getValue('instance');

      if (!is_numeric($nid)) {
        if (preg_match('/^(?:\s*|(.*) )?\[\s*nid\s*:\s*(\d+)\s*\]$/', $nid, $matches)) {
          $nid = $matches[2];
        }
      }

      if ($nid) {
        $form_state->setValue('instance', $nid);
      }
      else {
        // Unset it, or we'll erase the relationship (since the textfield is
        // actually blank).
        $form_state->unsetValue('instance');
      }
    }

    parent::optionsSubmit($form, $form_state);
  }

  /**
   * Clone a node before saving.
   *
   * {@inheritdoc}
   */
  function preSave(Drupal\Core\Entity\EntityStorageInterface $storage, $update = TRUE) {

    if ($this->getOption('clone_and_reference')) {
      module_load_include('inc', 'clone', 'clone.pages');
      $new_nid = clone_node_save($this->getOption('instance'));
      $this->setInstanceId($new_nid);
      $this->setOption('clone_and_reference', 0);
    }

    parent::preSave($storage, $update);
  }

  /**
   * On object write, set privacy on this node.
   *
   * {@inheritdoc}
   */
  function postSave(Drupal\Core\Entity\EntityStorageInterface $storage, $update = TRUE) {
    $privacy_enabled = $this->hasNodePrivacySupport() && $this->getOption('private');
    $external_node = $this->getInstanceId() > 0;
    if ($privacy_enabled && $external_node) {
      \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
    }

    parent::postSave($storage, $update);
  }

  /**
   * Freeze data to persist over cloning/exporting.
   * @return array
   *   An array of data to be frozen.
   */
  function freeze() {
    if ($this->getInstanceId() != $this->getCourse()->getNode()->nid) {
      // Don't freeze the course, if this course is part of the objects.
      $ice = new stdClass;
      $ice->node = $this->getNode();
      return $ice;
    }
  }

  /**
   * Thaw data frozen from an earlier export/clone.
   *
   * @param array $data
   *   Unfrozen data.
   *
   * @return int
   *   The new instance ID.
   */
  function thaw($ice) {
    $node = $ice->node;
    unset($node->nid);
    unset($node->vid);

    // Let other modules do special fixing up.
    $context = array('method' => 'save-edit');
    Drupal::moduleHandler()->alter('clone_node', $node, $context);

    node_save($node);
    $this->setInstanceId($node->id());
    return $this->getInstanceId();
  }

  function getCloneAbility() {
    return t('%object will be cloned as a node. Results may vary.', array('%object' => $this->getTitle()));
  }

  /**
   * Get the object title, or return this object's node's title if the option
   * is set.
   */
  function getTitle() {
    if ($this->getOption('use_node_title') && $this->getNode()) {
      return $this->getNode()->title;
    }
    else {
      return parent::getTitle();
    }
  }

  /**
   * Get this node object's node.
   *
   * @return NodeInterface
   */
  function getNode() {
    return \Drupal\node\Entity\Node::load($this->get('instance')->getString());
  }

  /**
   * Make the "Edit instance" link use a dialog.
   *
   * {@inheritdoc}
   */
  function getOptionsSummary() {
    $summary = parent::getOptionsSummary();
    if (is_a($summary['instance'], \Drupal\Core\GeneratedLink::class)) {
      $url = $this->getEditUrl();
      $url->setOption('query', \Drupal::service('redirect.destination')->getAsArray());
      $url->setOption('attributes', [
        'class' => 'use-ajax',
        'data-dialog-type' => 'modal',
        'data-dialog-options' => \Drupal\Component\Serialization\Json::encode(['width' => 800]),
      ]);
      $link = \Drupal\Core\Link::fromTextAndUrl(t('Edit instance'), $url)->toString();
      $summary['instance'] = $link;
    }
    return $summary;
  }

  /**
   * Show a warning if this object has an instance, but the node does not exist.
   */
  function getWarnings() {
    $warnings = parent::getWarnings();
    if ($this->getInstanceId() && !$this->getNode()) {
      $warnings[] = t('The content associated with this object has been deleted.<br/>Saving the course will create new content from the object settings.');
    }
    return $warnings;
  }

  /**
   * Deny access to objects without content.
   *
   * @todo conflicts with core D8
   */
  function Xaccess($op = 'view', $account = NULL) {
    if ($op == 'take' && !$this->getNode()) {
      return FALSE;
    }
    return parent::access($op, $account);
  }

}
