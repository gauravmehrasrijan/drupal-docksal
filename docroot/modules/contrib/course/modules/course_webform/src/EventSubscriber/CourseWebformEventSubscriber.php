<?php

namespace Drupal\course_webform\EventSubscriber;

use Drupal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CourseWebformEventSubscriber implements EventSubscriberInterface {

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
    if ($route_match->getRouteName() == 'entity.webform.canonical') {
      $webform = $route_match->getParameter('webform');
      // Process fulfillment or something
    }
  }

}
