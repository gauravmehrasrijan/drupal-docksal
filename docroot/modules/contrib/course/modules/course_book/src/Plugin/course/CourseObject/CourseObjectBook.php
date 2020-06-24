<?php

namespace Drupal\course_book\Plugin\course\CourseObject;

use Drupal;
use Drupal\course_content\Course\Object\CourseObjectNode;
use function content_access_get_per_node_settings;
use function content_access_save_per_node_settings;
use function course_book_count;
use function course_book_override_outline_list_item;
use function node_type_get_names;

/**
 * @CourseObject(
 *   id = "book",
 *   label = "Book",
 *   handlers = {
 *     "fulfillment" = "\Drupal\course_book\Plugin\course\CourseObject\CourseObjectBookFulfillment"
 *   }
 * )
 */
class CourseObjectBook extends CourseObjectNode {

  /**
   * Course node context handler callback.
   */
  public static function getNodeInstances($node) {
    if (!empty($node->book['bid'])) {
      return array($node->book['bid']);
    }
  }

  function getNodeTypes() {
    if (Drupal::currentUser()->hasPermission('administer book outlines')) {
      return array_keys(node_type_get_names());
    }
    else {
      $config = \Drupal::config('book.settings');
      return $config->get('allowed_types');
    }
  }

  /**
   * Make the book.
   */
  public function createInstance($node = NULL) {
    $node = \Drupal\node\Entity\Node::create(['type' => $this->getOption('node_type')]);
    $node->book['bid'] = 'new';
    parent::createInstance($node);
  }

  function optionsDefinition() {
    $defaults = parent::optionsDefinition();
    $defaults['node_type'] = \Drupal::config('course_book.settings')->get('default_node_type', 'book');
    $defaults['book_tracking'] = 'all';
    $defaults['outline_list_item_type'] = 'active_tree';
    return $defaults;
  }

  function optionsForm(&$form, &$form_state) {
    $config = $this->getOptions();
    parent::optionsForm($form, $form_state);
    $form['book_tracking'] = array(
      '#title' => t('Completion criteria'),
      '#type' => 'select',
      '#options' => array(
        'one' => t('View any page'),
        'all' => t('View all pages'),
      ),
      '#default_value' => $config['book_tracking'],
    );

    // Add a book-specific configuration for course outline list item type, only
    // if the standard course list outline handler is selected.
    if ($this->getCourse()->get('outline')->getString() == 'course') {
      $form['outline_list_item_type'] = array(
        '#title' => t('Course outline list item type'),
        '#type' => 'select',
        '#options' => array(
          'all_pages' => t('All book pages as an expanded, nested list'),
          'active_tree' => t('Only the active book menu tree items, with a count indicator'),
          'count' => t('A count indicator only'),
        ),
        '#default_value' => $config['outline_list_item_type'],
      );
    }
  }

  /**
   * Grade (track) the book based on the fulfillment data.
   */
  function grade($user) {
    /* @var $book_manager Drupal\book\BookManager */
    $book_manager = \Drupal::service('book.manager');
    $toc = $book_manager->getTableOfContents($this->getInstanceId(), \Drupal::config('course_book.settings')->get('depth', 100));

    if (course_book_count($this->getInstanceId()) == 0) {
      // Book has no pages. Complete object.
      $this->getFulfillment($user)->setComplete(1)->save();
      return;
    }

    if ($this->getOption('book_tracking') == 'all') {
      $nids = array_keys($toc);
      $fulfillment = $this->getFulfillment($user)->getOption('book_fulfillment');
      $viewed = $fulfillment ? array_keys(array_filter($fulfillment)) : array();
      if (!array_diff($nids, $viewed)) {
        $this->getFulfillment($user)->setComplete(1)->save();
      }
    }
    elseif ($this->getOption('book_tracking') == 'one') {
      $this->getFulfillment($user)->setComplete(1)->save();
    }
  }

  /**
   * Overrides navigation links.
   */
  public function overrideNavigation() {
    $links = parent::overrideNavigation();

    $route_match = Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.node.canonical') {
      $node = $route_match->getParameter('node');
      /* @var $book_outline Drupal\book\BookOutline */
      $book_outline = \Drupal::service('book.outline');
      if (isset($node->book)) {
        $book_link = $node->book;
        if ($prev = $book_outline->prevLink($book_link)) {
          $links['prev'] = \Drupal\Core\Link::createFromRoute(t('Previous'), 'entity.node.canonical', ['node' => $prev['nid']]);
        }
        if ($next = $book_outline->nextLink($book_link)) {
          $links['next'] = \Drupal\Core\Link::createFromRoute(t('Next'), 'entity.node.canonical', ['node' => $next['nid']]);
        }
      }

      return $links;
    }
  }

  /**
   * Overrides a course outline list item.
   */
  public function overrideOutlineListItem(&$item) {
    // Check that course list outline handler is selected.
    if ($this->getCourse()->get('outline')->getString() == 'course') {
      $type = $this->getOption('outline_list_item_type');
      // Override the list item by reference.
      course_book_override_outline_list_item($item, $this, $type);
    }
  }

  public function getCloneAbility() {
    return t('%title will only clone the first page.', array('%title' => $this->getTitle()));
  }

  /**
   * Override of CourseObjectNode::save()
   *
   * We have to remove the stock "view" content access permissions on Books, if
   * node_access_book is enabled. Otherwise, users outside of the course can
   * still access child book pages of a private book parent.
   */
  public function save() {
    // Take care of the parent book page.
    parent::save();

    if ($this->hasNodePrivacySupport() && $this->getOption('private') && Drupal::moduleHandler()->moduleExists('node_access_book')) {
      // Remove "view" permissions on all the child pages.
      $flat = array();
      $tree = menu_tree_all_data($this->getNode()->book['menu_name']);
      _book_flatten_menu($tree, $flat);
      foreach ($flat as $item) {
        $nid = str_replace('node/', '', $item['link_path']);
        $node = \Drupal\node\Entity\Node::load($nid);

        $settings = content_access_get_per_node_settings($node);
        $settings['view'] = array();
        content_access_save_per_node_settings($node, $settings);

        // Resave node to update access.
        node_access_acquire_grants($node);
      }
    }
  }

  /**
   * Override of CourseObjectNode::freeze().
   *
   * Do not freeze the parent book ID.
   *
   * course_book_node_insert() stumbles if this is set and we are cloning a
   * book.
   */
  public function freeze() {
    $ice = parent::freeze();
    unset($ice->node->book['bid']);
    return $ice;
  }

}
