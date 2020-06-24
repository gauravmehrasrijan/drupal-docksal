<?php

namespace Drupal\course_book\EventSubscriber;

use Drupal;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function course_get_course_object;

class CourseBookEventSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 28],
    ];
  }

  /**
   * If the current node is a course object, fulfill it for the current user.
   *
   * @param GetResponseEvent $event
   */
  public function onRequest(GetResponseEvent $event) {
    $route_match = Drupal::routeMatch();
    if ($route_match->getRouteName() == 'entity.node.canonical') {
      $node = $route_match->getParameter('node');
      $account = Drupal::currentUser();

      $type = $node->bundle();

      if (book_type_is_allowed($type)) {
        $search = !empty($node->book['bid']) ? $node->book['bid'] : $node->id();
        if ($courseObject = course_get_course_object('book', $search)) {
          $options = array();
          // Mark this node as fulfillment in course_book's fulfillment tracking.
          if (!empty($node->book['nid'])) {
            $options['book_fulfillment'][$node->book['nid']] = TRUE;
            $courseObject->getFulfillment($account)->addOptions($options)->save();
          }
          // "Grade" the book based on previous book page views.
          $courseObject->grade($account);
        }
      }
    }
  }

}
