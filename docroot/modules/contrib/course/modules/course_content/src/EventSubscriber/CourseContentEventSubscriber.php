<?php

namespace Drupal\course_content\EventSubscriber;

use Drupal;
use Drupal\node\Entity\NodeType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function course_get_course_object;

class CourseContentEventSubscriber implements EventSubscriberInterface {

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

      $type = NodeType::load($node->bundle());
      if ($type->getThirdPartySetting('course_content', 'use')) {
        $account = Drupal::currentUser();
        if ($courseObject = course_get_course_object('content', $node->id())) {
          $courseObject->getFulfillment($account)->setComplete()->save();
        }
      }
    }
  }

}
