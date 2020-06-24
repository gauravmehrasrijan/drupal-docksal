<?php

namespace Drupal\course\EventSubscriber;

use Drupal;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function course_get_course_object;

class CourseEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      // Set priority to 28 so we can get around the page cache.
      KernelEvents::REQUEST => ['onRequest', 28],
    ];
  }

  /**
   * Check if the current node will fulfill an object.
   *
   * @param GetResponseEvent $event
   */
  public function onRequest(GetResponseEvent $event) {
    course_context();

    if (!$course = course_get_context()) {
      // Set course context for all modules that define course context handlers.
      $handlers = course_get_handlers('object');
      if (is_array($handlers)) {
        foreach ($handlers as $handler) {
          // We expect query parameters suitable for course_determine_context().
          if ($params = call_user_func(array($handler['class'], 'context'))) {
            if (is_array($params) && isset($params['object_type']) && isset($params['instance'])) {
              if ($course = course_determine_context($params['object_type'], $params['instance'])) {
                // Set the course context.
                course_set_context($course);

                // Find and set the active object.
                $course_object = course_get_course_object($params['object_type'], $params['instance'], $course);
                $_SESSION['course'][$course->id()]['taking']['active'] = $course_object->id();
              }
            }
          }
        }
      }
    }
  }

}
