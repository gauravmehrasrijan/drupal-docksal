<?php

namespace Drupal\course\Form;

use Drupal\Component\Utility\SortArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use function course_editing_start;
use function course_get_course_object;
use function course_get_course_object_by_id;
use function course_get_handlers;
use function drupal_set_message;

class CourseOutlineForm extends FormBase {

  /**
   * Comparator function for course outline weights.
   */
  private static function sortCourseOutline($a, $b) {
    if (is_object($a)) {
      return $a->getOption('weight') < $b->getOption('weight') ? -1 : 1;
    }
    else {
      return SortArray::sortByWeightElement($a, $b);
    }
  }

  public function buildForm(array $form, FormStateInterface $form_state, $course = NULL) {
    // Wrapper for objects and more button.
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    // Shortcut to the course outline table.
    $cform = &$form['course_outline'];
    $cform['#prefix'] = '<div class="clear-block" id="course-outline-wrapper">';
    $cform['#suffix'] = '</div>';
    $cform['#attached']['library'][] = 'course/admin-css';

    // Add object if button was pressed.
    $this->addObject($form, $form_state);

    // Grab initial list of objects from DB or session.
    if (!empty($_SESSION['course'][$course->id()]['editing'])) {
      $objects = $_SESSION['course'][$course->id()]['editing'];
    }
    else if ($objects = $course->getObjects()) {
      // Great.
    }
    else {
      $objects = array();
    }

    // Sort list of objects we pulled from session or DB by weight for proper
    // display.
    uasort($objects, 'static::sortCourseOutline');

    $cform['#title'] = t('Course objects');
    //$form['#theme'] = 'course_outline_overview_form';

    if (empty($_POST) && !empty($_SESSION['course'][$course->id()]['editing'])) {
      \Drupal::messenger()->addWarning(t('Changes to this course have not yet been saved.'));
    }

    $handlers = course_get_handlers('object');

    // Wrapper for just the objects.
    $cform['#tree'] = TRUE;
    $cform['#type'] = 'table';
    $cform['#id'] = 'edit-course-outline';
    $cform['#empty'] = $this->t('No objects. Add an object!');
    $cform['#header'] = [
      $this->t('Description'),
      $this->t('Object'),
      $this->t('Actions'),
      $this->t('Weight'),
    ];
    $object_counts = array();
    if (count($objects)) {
      foreach (array_keys($objects) as $uniqid) {
        if ($courseObject = course_get_course_object_by_id($uniqid)) {
          $rform = $this->formObject($courseObject);

          // Keep track of how many of each course object we have.
          // @kludge probably some simpler way to do this effectively
          if (!isset($object_counts[$courseObject->getComponent()])) {
            $object_counts[$courseObject->getComponent()] = 1;
          }
          else {
            $object_counts[$courseObject->getComponent()]++;
          }

          $cform[$uniqid] = $rform;
        }
      }
    }

    // Add object button and select box for new objects.
    $object_types = array(
      '' => '- ' . t('select object') . ' -',
    );
    if ($handlers) {
      foreach ($handlers as $key => $object_info) {
        $class = $object_info['class'];
        $max_object_count = call_user_func(array($class, 'getMaxOccurences'));
        $under_limit = !$max_object_count || !(isset($object_counts[$key]) && $object_counts[$key] >= $max_object_count);
        if ($under_limit && empty($object_info['legacy'])) {
          $object_types[$key] = $object_info['label'];
        }
      }
    }

    $form['more'] = array(
      '#type' => 'markup',
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    );

    $form['more']['add_another'] = array(
      '#type' => 'button',
      '#value' => t('Add object'),
      '#ajax' => array(
        'callback' => '::ajaxCallback',
        'method' => 'replace',
        'wrapper' => 'course-outline-wrapper',
      ),
      '#weight' => 20,
    );

    // Sort course object types
    asort($object_types);

    $form['more']['object_type'] = array(
      '#type' => 'select',
      '#options' => $object_types,
      '#weight' => 10,
    );

    $form['actions']['#type'] = 'actions';

    // Submit and reset buttons.
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save outline'),
    );

    if (!empty($_SESSION['course'][$course->id()]['editing'])) {
      $form['actions']['reset'] = array(
        '#type' => 'submit',
        '#value' => t('Revert'),
        '#submit' => ['::resetForm'],
      );
    }

    $cform['#tabledrag'][] = [
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'course-object-weight',
    ];

    return $form;
  }

  public function getFormId(): string {
    return 'course_outline_overview_form';
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $course = $form_state->getBuildInfo()['args'][0];

    // Get form state values for object elements on the course outline overview:
    // - An associative array of course objects, keyed by ID. The ID for already
    //   saved objects is {course_object}.coid, but for AHAH created objects the
    //   key is a generated unique ID until save.
    //   - coid: The key loaded from the database. If empty, the object is new.
    //   - module: The implementing module name (course_quiz etc).
    //   - object_type: The course object key as defined by
    //     hook_course_handlers().
    $objects = $form_state->getValue('course_outline');

    // Sort by weight so we can renumber.
    uasort($objects, 'static::sortCourseOutline');

    foreach ($objects as $object_key => $object) {
      // Load object from session.
      /* @var $courseObject \Drupal\course\Entity\CourseObject */
      if (!$courseObject = _course_get_course_object_by_uniqid($object_key)) {
        $courseObject = \Drupal\course\Entity\CourseObject::load($object_key);
      }
      if ($courseObject->id()) {
        // This isn't new.
        $courseObject->enforceIsNew(FALSE);
      }

      // Renumber weights to the way draggable table would do it in case of no JS.
      $courseObject->setOption('weight', $object['weight']);

      if ($courseObject->getOption('delete')) {
        // Delete the course object.

        if ($courseObject->getOption('delete_instance')) {
          // Also delete the course object's content.
          $courseObject->deleteInstance();
        }

        $courseObject->delete();
      }
      else {
        $courseObject->save();
      }
    }

    // Clear the editing session.
    unset($_SESSION['course'][$course->id()]['editing']);

    \Drupal::messenger()->addStatus(t('Updated course.'));

    $form_state->setRedirect('course.outline', ['course' => $course->id()]);
  }

  function addObject(array &$form, FormStateInterface $form_state) {
    // Check if "Add object" was clicked.

    if ($form_state->getTriggeringElement()['#value'] == 'Add object' && !empty($form_state->getValues()['more']['object_type'])) {
      $course = $form_state->getBuildInfo()['args'][0];
      // Ensure that we cached the course.
      course_editing_start($course);

      // Create a new course object in the session, and let the rest of the form
      // builder handle it.
      $obj_uniqid = uniqid('course_object_');
      $_SESSION['course'][$course->id()]['editing'][$obj_uniqid] = array();

      // Populate temporary course object, save it in the session.
      $new = array();
      $new['weight'] = 0;

      // Get highest weight and add to it.
      if (isset($form_state->getValues()['course_outline']['objects'])) {
        foreach ($form_state->getValues()['course_outline']['objects'] as $key => $object) {
          if ($object['weight'] >= $new['weight']) {
            $new['weight'] = $object['weight'] + 1;
          }
        }
      }

      $new['cid'] = $course->id();
      $new['coid'] = $obj_uniqid;
      $new['object_type'] = $form_state->getValues()['more']['object_type'];
      $_SESSION['course'][$course->id()]['editing'][$obj_uniqid] = $new;
      $form_state->setValue('last', $obj_uniqid);
    }
  }

  /**
   * Handle the "Add object" AJAX event.
   */
  function ajaxCallback($form, FormStateInterface $form_state) {
    return $form['course_outline'];

    // Maybe pop up a modal later.
    /**
      $course = $form_state->getBuildInfo()['args'][0];
      $object_id = $form_state->getValue('last');
      $object_form = \Drupal::formBuilder()->getForm('\Drupal\course\Form\CourseObjectForm', $course, $object_id);
      $object_form['#action'] = \Drupal::url('course.object.options', ['course' => $course->id(), 'course_object' => $object_id], ['query' => ['destination' => \Drupal::destination()]]);
      $command = new \Drupal\Core\Ajax\OpenModalDialogCommand('New object', $object_form, ['width' => '75%']);
      $response = new \Drupal\Core\Ajax\AjaxResponse();
      $response->addCommand($command);
      return $response;
     */
  }

  /**
   * Form constructor for a course object.
   *
   * To be re-used in listing and creating new course objects.
   */
  function formObject($courseObject = NULL) {
    $rform['#attributes']['class'][] = 'draggable';
    $rform['#tree'] = TRUE;
    $uniqid = $courseObject->getId();

    // Do not use prefix/suffix because markup only renders with a value, and we
    // need the wrapper before the title is saved for ajax population after each
    // settings modal update.
    $title = $courseObject->getTitle();
    $rform['description']['title'] = array(
      '#prefix' => '<div id="title-' . $uniqid . '">',
      '#suffix' => '</div>',
      '#type' => 'markup',
      '#plain_text' => $title,
    );

    $rform['description']['summary'] = array(
      '#prefix' => '<div id="summary-' . $uniqid . '">',
      '#suffix' => '</div>',
      '#theme' => 'item_list',
      '#items' => $courseObject->renderOptionsSummary(),
    );

    $handlers = course_get_handlers('object');
    if (empty($handlers[$courseObject->getOption('object_type')])) {
      $show_object_name = t('Missing CourseObject handler for <br/><i>@t</i>', array('@t' => $courseObject->getOption('object_type')));
    }
    else {
      $show_object_name = $handlers[$courseObject->getOption('object_type')]['label'];
    }
    $rform['object_type_show'] = array(
      '#type' => 'markup',
      '#markup' => Xss::filterAdmin($show_object_name),
    );

    // Placeholder for the settings link, it gets added after this function runs
    // in course_outline_overview_form(). #value needs a space for the prefix and
    // suffix to render.
    // Settings link for saved objects.
    $text = t('Settings');
    $l_options = array(
      'query' => array('destination' => "course/{$courseObject->getCourse()->id()}/outline"),
      'attributes' => [
        'data-dialog-type' => 'modal',
        'class' => 'use-ajax',
        'data-dialog-options' => \Drupal\Component\Serialization\Json::encode(['width' => 800]),
      ],
    );
    $url = Url::fromRoute('course.object.options', [
        'course' => $courseObject->getCourse()->id(),
        'course_object' => $uniqid,
        ], $l_options);
    $rform['options']['#markup'] = Link::fromTextAndUrl($text, $url)->toString();

    $rform['weight'] = array(
      '#title' => $this->t('Weight for @title', ['@title' => $title]),
      '#type' => 'weight',
      '#title_display' => 'invisible',
      '#size' => 3,
      '#delta' => 100,
      '#default_value' => $courseObject->getOption('weight'),
      '#attributes' => array(
        'class' => array('course-object-weight'),
      ),
    );

    if ($courseObject->getOption('delete')) {
      $rform['#attributes']['class'][] = 'deleted';
    }

    return $rform;
  }

  /**
   * Submit handler for resetting a Course back to stored defaults.
   */
  function resetForm(&$form, FormStateInterface $form_state) {
    $course = $form_state->getBuildInfo()['args'][0];
    unset($_SESSION['course'][$course->id()]['editing']);
  }

}
