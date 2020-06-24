<?php

namespace Drupal\course_certificate\Controller;

use Drupal;
use Drupal\certificate\Entity\CertificateMapping;
use Drupal\certificate\Entity\CertificateTemplate;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\course\Entity\Course;
use function count;
use function render;

/**
 * An example controller.
 */
class CertificateTabController extends ControllerBase {

  /**
   *  Helper to plugin current user when not provided in path
   * @param Course $course
   * @return main cert tab
   */
  public function renderDefaultCertificateTab(Course $course) {
    $account = Drupal::currentUser();
    return $this->renderCertificateTab($course, $account);
  }

  /**
   *  Helper to plugin current user when not provided in path
   * @param Course $course
   * @return main cert tab
   */
  public function accessDefaultTab(Course $course) {
    $account = Drupal::currentUser();
    return $this->accessTab($course, $account);
  }

  /**
   * @param EntityInterface $course
   *   The entity this belongs to
   * @param AccountInterface $account
   * The user account to check
   * @return \Drupal\Core\Access\AccessResultInterface
   *   An access result
   */
  public function accessTab(Course $course, AccountInterface $account = NULL) {
    $has_object = FALSE;
    $account = $account ?? Drupal::currentUser();
    $admin = $account->hasPermission('administer certificates');
    $view_all = $account->hasPermission('view all user certificates');
    $access_result = AccessResult::forbidden("Access not granted");

    if (!$account->id()) {
      return AccessResult::forbidden("Not a valid user");
    }

    // Does the course have a certificate object?
    foreach ($course->getObjects() as $courseObject) {
      if ($courseObject->getComponent() == 'certificate') {
        $has_object = TRUE;
        break;
      }
    }

    if (!$has_object) {
      return AccessResult::forbidden("No certificate object provided");
    }

    // Are they enrolled?
    $enrollments = $this->entityTypeManager()->getStorage('course_enrollment')->loadByProperties(['uid' => $account->id(), 'cid' => $course->id()]);
    if (empty($enrollments)) {
      $access_result = AccessResult::forbidden();
    }
    else {
      $enrollment = reset($enrollments);
      // Are they an admin or have they completed the course?
      if ($admin || $view_all || $enrollment->isComplete()) {
        $access_result = AccessResult::allowed();
      }
    }
    return $access_result;
  }

  /**
   * Full tab
   * @param Course $course
   * @param AccountInterface $account
   * @return type
   */
  function renderCertificateTab(Course $course, AccountInterface $account) {
    // Get all templates for this entity combo
    $render = [];
    $valid_certs = [];
    $global_certs = CertificateMapping::getGlobalCertificateMappings();
    $certificate_mappers = Drupal::service('plugin.manager.certificate_mapper');
    $map_defs = $certificate_mappers->getDefinitions();
    $certs = $course->get('certificate')->referencedEntities();

    //Default to load a page
    $render['info']['#markup'] = '';
    foreach ($map_defs as $map_key => $maps) {
      $plugin = $certificate_mappers->createInstance($map_key, ['of' => 'configuration values']);
      $matches = $plugin->processMapping($course, $account) ?? [];

      foreach ($matches as $match) {
        foreach ($certs as $local) {
          if ($local->isMatch($map_key, $match)) {
            $valid_certs["$map_key.$match"] = $local->get('cid')->value;
          }
        }

        // If local is not set, check the global mappings
        if (!isset($valid_certs["$map_key.$match"])) {
          $render['table'] = [
            '#type' => 'table',
            '#header' => [
              $this->t('Type'),
              $this->t('Download'),
            ]
          ];
          foreach ($global_certs as $global) {
            if ($global->isMatch($map_key, $match) && $global->get('cid')->value !== '-1') {
              $valid_certs["$map_key.$match"] = $global->get('cid')->value;
            }
          }
        }
        // Remove when prevented
        elseif ($valid_certs["$map_key.$match"] == '-1') {
          unset($valid_certs["$map_key.$match"]);
        }
      }
    }

    // Return markup if we need to present messages
    if (count($valid_certs) > 1) {
      $render['info']['#markup'] = ' You are eligible for multiple certificates.';
    }
    if (empty($valid_certs)) {
      $render['info']['#markup'] = 'You are not eligible for a certificate';
    }

    foreach ($valid_certs as $cert_name => $val) {
      $opts = ['course' => $course->id(), 'account' => $account->id(), 'template' => $val];
      $render['table'][$val] = [
        'type' => ['#markup' => $cert_name],
        'download' => Link::createFromRoute(t('Download certificate'), 'certificate.course.pdf', $opts)->toRenderable(),
      ];
    }

    return $render;
  }

  /**
   * Downloads
   *
   * @param Course $course
   * @param AccountInterface $account
   * @param CertificateTemplate $template
   * @return type
   */
  function accessPdf(Course $course, AccountInterface $account, CertificateTemplate $template) {
    return $this->accessTab($course, $account);
  }

  /**
   * Stream a PDF to the browser
   * @param Course $course
   * @param AccountInterface $account
   * @param CertificateTemplate $template
   * @return type
   */
  function returnPdf(Course $course, AccountInterface $account, CertificateTemplate $template) {
    $pdf_gen = $template->loadPrintableEngine();
    // Check for a PDF engine
    if ($pdf_gen === FALSE) {
      $current_user = \Drupal::currentUser();
      $msg = t('Current site configuration does not allow PDF file creation. Please contact an administrator.');

      if ($current_user->hasPermission('administer printable')) {
        $url = Url::fromRoute('printable.format_configure_pdf');
        $link = Link::createFromRoute('configure a PDF library', 'printable.format_configure_pdf');
        $msg = t('Please @link to print certificates.', ['@link' => $link->toString()]);
      }

      return ['#markup' => $msg];
    }

    // Everything is configured, build the PDF
    $render = $template->renderView($account, $course);
    $pdf_gen->addPage(render($render));

    return $pdf_gen->stream($pdf_gen->getObject()->getPdfFilename());
  }

}
