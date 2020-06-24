<?php

namespace Drupal\course\Controller;

use Drupal;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\course\Entity\Course;
use Drupal\course\Plugin\CourseOutlinePluginBase;
use Drupal\course\Plugin\CourseOutlinePluginManager;
use const REQUEST_TIME;
use function drupal_set_message;

class CourseController extends EntityController {

  /**
   * Access callback for completion page.
   */
  function completionAccess(Course $course) {
    $account = \Drupal::currentUser();
    return Drupal\Core\Access\AccessResult::allowedIf($course->isEnrolled($account));
  }

  /**
   * Render a landing page for course completion.
   *
   * @param Course $course
   *
   * @return array
   *   Render array for the completion landing page.
   */
  function renderComplete(Course $course) {
    $account = \Drupal::currentUser();

    // User's course record.
    $course_enrollment = $course->getEnrollment($account);

    // Render array.
    $page = array();

    // Links.
    $links = array();

    $url = $course->getUrl();
    $url->setOption('title', t('Return to the course to view course details and material.'));
    $links['course'] = \Drupal\Core\Link::fromTextAndUrl(t('Return to course'), $url);

    if ($course_enrollment->isComplete()) {
      // Allow modules to add links to the course completion landing page, such as
      // post-course actions.
      Drupal::moduleHandler()->alter('course_outline_completion_links', $links, $course, $account);
    }
    else {
      Drupal::moduleHandler()->alter('course_outline_incomplete_links', $links, $course, $account);
    }


    $page['#title'] = $course_enrollment->isComplete() ? t('Course complete') : t('Remaining requirements');

    $objects = $course->getObjects();
    $items = array();
    foreach ($objects as $courseObject) {
      if ($courseObject->access('see')) {
        // Find required course objects the user has not yet completed.
        $req = $courseObject->getFulfillment($account);
        $status_css = $req->isComplete() ? 'complete' : 'incomplete';
        $status_img = $req->isComplete() ? 'core/misc/icons/73b355/check.svg' : ($req->getCourseObject()->isRequired() ? 'core/misc/icons/e32700/error.svg' : 'core/misc/icons/e29700/warning.svg');
        $status_class = 'course-complete-item-' . $status_img;
        $status_optional = ' (' . (!$req->getCourseObject()->isRequired() ? t('optional') : t('required')) . ')';
        if ($courseObject->access('take')) {
          $link = \Drupal\Core\Link::fromTextAndUrl($req->getCourseObject()->getTitle(), $courseObject->getUrl())->toString();
        }
        else {
          $link = $req->getCourseObject()->getTitle();
        }
        $items[] = array(
          'data' => array(
            array(
              'data' => ['#theme' => 'image', '#uri' => $status_img, '#alt' => $status_css],
              'width' => 20,
              'class' => array('course-complete-item-status'),
            ),
            array(
              'data' => ['#markup' => $link . $status_optional . '<br/>' . $req->getCourseObject()->getStatus()],
              'class' => array('course-complete-item-title'),
            ),
          ),
          'class' => array($status_class),
        );
      }
    }

    if ($course_enrollment->isComplete()) {
      $message = t('You have completed the course. Use the links below to review the course content.');
    }
    else {
      $message = t('This course is not complete. Use the links below to access the remaining course content.');
    }

    $page['course_header'] = array(
      '#type' => 'item',
      '#title' => t('Thank you for participating in this course.'),
      '#description' => $message,
      '#description_display' => TRUE,
      '#weight' => 1,
    );

    $page['course_completion_requirements'] = array(
      '#theme' => 'table',
      '#header' => NULL,
      '#rows' => $items,
      '#weight' => 3,
      '#attributes' => array('class' => array('course-complete-items')),
    );

    foreach ($links as $key => $link) {
      $element = array(
        '#title' => $link->toString(),
        '#description' => $link->getUrl()->getOption('title'),
        '#type' => 'item',
        '#description_display' => TRUE,
      );
      $page['course_links'][$key] = $element;
    }

    $page['course_links']['#weight'] = 2;

    $page['#cache']['max-age'] = 0;

    return $page;
  }

  /**
   * Take a course.
   *
   * - Enroll the user, if allowed.
   * - Block the user if not allowed.
   * - Fire the outline handler.
   */
  public function renderTake(Course $course) {
    $account = Drupal::currentUser();

    $enroll_access = $course->access('enroll', $account, TRUE);
    $enrollment = $course->getEnrollment($account);

    /* @var $enrollment Drupal\course\Entity\CourseEnrollment */
    if ($enroll_access->isAllowed() && (!$enrollment || $enrollment->get('timestamp')->isEmpty())) {
      // User can enroll in this course and user is not enrolled. Check for
      // enrollment fields or enroll the user.
      $instances = Drupal::service('entity_field.manager')->getFieldDefinitions('course_enrollment', $course->get('enrollment_type')->getString());
      foreach ($instances as $field_name => $field) {
        if (is_a($field, Drupal\field\Entity\FieldConfig::class)) {
          /* @var $field Drupal\field\Entity\FieldConfig */
          if ($field->getThirdPartySetting('course', 'show_field')) {
            // At least one field must be shown when enrolling. Display the user
            // enrollment form.
            if (!$enrollment) {
              // Create a new enrollment.
              $enrollment = Drupal\course\Entity\CourseEnrollment::create([
                  'cid' => $course->id(),
                  'uid' => $account->id(),
                  'type' => $course->get('enrollment_type')->getString(),
                  'timestamp' => REQUEST_TIME,
              ]);
            }
            else {
              $enrollment->set('timestamp', REQUEST_TIME);
            }
            $redirect_url = \Drupal\Core\Url::fromRoute('course.take', ['course' => $course->id()]);
            $form = \Drupal::service('entity.form_builder')->getForm($enrollment, 'default', ['redirect' => $redirect_url]);

            return $form;
          }
        }
      }

      // No fields to show. Check for enrollment. If it does not exist, create it.
      if (empty($enrollment)) {
        $enrollment = \Drupal\course\Entity\CourseEnrollment::create(array(
            'cid' => $course->id(),
            'uid' => $account->id(),
            'type' => $course->enrollment_type->getString(),
        ));
        $enrollment->save();
      }
    }

    $take_access = $course->access('take', $account, TRUE);
    if ($take_access->isAllowed()) {
      // User has access to take this course.
      if ($enrollment->get('timestamp')->isEmpty()) {

        // If user hasn't started course, mark start of enrollment.
        $enrollment->set('timestamp', REQUEST_TIME)->save();
        \Drupal::messenger()->addStatus(t('Your enrollment in this course has been recorded.'));
      }

      // Display the configured outline handler output.
      /* @var $pluginManager CourseOutlinePluginManager */
      $pluginManager = Drupal::service('plugin.manager.course.outline');
      /* @var $outlinePlugin CourseOutlinePluginBase */
      $outlinePlugin = $pluginManager->createInstance($course->get('outline')->getString());
      $outline = $outlinePlugin->render($course, $account);

      if (!$outline) {
        $outline['#markup'] = t('No learning objects are available this time.');
      }

      // Set page title to title of this course.
      $outline['#title'] = $course->get('title')->getString();

      $outline['#cache']['max-age'] = 0;
      return $outline;
    }
    else {
      $message = $take_access->getReason();
      if (!$message) {
        $message = t('Sorry, you do not have access to take this course.');
      }
      \Drupal::messenger()->addError($take_access->getReason());
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
  }

}
