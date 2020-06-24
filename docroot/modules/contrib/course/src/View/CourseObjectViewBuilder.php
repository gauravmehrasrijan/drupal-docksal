<?php

namespace Drupal\course\View;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Link;

class CourseObjectViewBuilder extends EntityViewBuilder {

  public function build(array $build) {
    $build = parent::build($build);

    $courseObject = $build['#course_object'];
    // When viewing, we use the current user.
    $account = Drupal::currentUser();

    $step = array();
    $step['id'] = 'course-object-' . $courseObject->getId();
    $step['image'] = '';
    $step['status'] = '';

    if ($courseObject->access('see', $account)) {
      if ($courseObject->access('take', $account)) {
        // User can take this course object.
        $step['link'] = $courseObject->getUrl();
        $step['class'][] = 'accessible';
        $step['status'] = t('Not started');

        // Step is complete.
        if ($courseObject->getFulfillment($account)->isComplete()) {
          $step['class'][] = 'completed';
          $step['status'] = t('Complete');
          $step['image'] = 'core/misc/icons/73b355/check.svg';
        }
        elseif ($courseObject->getFulfillment($account)->getId()) {
          $step['status'] = t('In progress');
          $step['class'][] = 'in-progress';
          $step['image'] = '';
        }
        if ($courseObject->getCourse()->getActive() === $courseObject) {
          $step['class'][] = 'active';
        }
      }
      else {
        // User cannot access this step yet.
        $step['class'] = array('not-accessible');
        $step['status'] = implode('<br/>', $courseObject->getAccessMessages());
      }

      if ($courseObject->isRequired()) {
        $step['class'][] = 'required';
      }

      $step['class'][] = Html::cleanCssIdentifier($courseObject->getComponent());

      $img = !empty($step['image']) ? ['#theme' => 'image', '#uri' => $step['image'], '#alt' => strip_tags($step['status'])] : [];


      $build['course_outline_image'] = $img;
      $build['course_outline_link'] = array(
        '#markup' => $courseObject->access('take', $account) ? Link::fromTextAndUrl($courseObject->getTitle(), $courseObject->getUrl())->toString() : $courseObject->getTitle(),
      );
      $build['course_outline_status'] = array(
        '#markup' => $step['status'],
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      );

      // No cache as this list always changes.
      $build['#cache']['max-age'] = 0;
    }

    return $build;
  }

}
