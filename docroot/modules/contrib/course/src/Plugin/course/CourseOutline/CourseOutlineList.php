<?php

namespace Drupal\course\Plugin\course\CourseOutline;

use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\course\Entity\Course;
use Drupal\course\Plugin\CourseOutlinePluginBase;
use function drupal_render;
use function entity_load;
use function entity_view;
use function render;

/**
 * @CourseOutline(
 *   id = "course",
 *   label = @Translation("Course"),
 *   description = @Translation("Displays course objects in an HTML list."),
 * )
 */
class CourseOutlineList extends CourseOutlinePluginBase {

  /**
   * {@inheritdoc}
   */
  function render(Course $course, AccountInterface $account) {
    // Iterate over objects.
    $workflow = array();
    $img = NULL;
    foreach ($course->getObjects() as $key => $courseObject) {
      if ($courseObject->access('see', $account)) {
        // The item will be in the list only if the user can see it. If they can
        // take it, entity_view() will output a link instead of text.
        $entity = \Drupal\course\Entity\CourseObject::load($courseObject->getId());
        $render_controller = \Drupal::entityTypeManager()
          ->getViewBuilder('course_object');
        $item = $render_controller->view($entity);

        if ($courseObject->access('take', $account)) {
          // User can take this course object.
          $item['#class'][] = 'accessible';

          // Step is complete.
          if ($courseObject->getFulfillment($account)->isComplete()) {
            $item['#class'][] = 'completed';
          }
          elseif ($courseObject->getFulfillment($account)->getId()) {
            $item['#class'][] = 'in-progress';
          }
          if ($course->getActive() === $courseObject) {
            $item['#class'][] = 'active';
          }
        }

        // Allow other modules to modify this list item.
        $courseObject->overrideOutlineListItem($item);

        // Add this item to the list.
        $workflow[] = $item;
      }
    }

    if ($course->getTracker($account)->isComplete()) {
      $image = [
        '#uri' => 'core/misc/icons/73b355/check.svg',
        '#alt' => t('An icon'),
        '#theme' => 'image',
      ];
      $workflow[] = array(
        '#markup' => render($image) . Link::fromTextAndUrl(t('Complete'), Url::fromRoute('course.complete', ['course' => $course->id()]))->toString(),
        '#id' => 'complete',
      );
    }

    $output = [];
    if ($workflow) {
      $page = [];
      $page['course_outline']['#theme'] = 'item_list';
      $page['course_outline']['#items'] = $workflow;
      return $page;
    }
    return $output;
  }

}
