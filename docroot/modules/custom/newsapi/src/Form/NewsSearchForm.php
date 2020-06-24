<?php

namespace Drupal\newsapi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\webprofiler\Form\FormBuilderWrapper;
use Drupal\newsapi\Services\NewsApiService;

/**
 * Class NewsSearchForm.
 */
class NewsSearchForm extends FormBase {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\DependencyInjection\ContainerBuilder definition.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $serviceContainer;

  /**
   * Symfony\Component\EventDispatcher\EventSubscriberInterface definition.
   *
   * @var \Symfony\Component\EventDispatcher\EventSubscriberInterface
   */
  protected $ajaxResponseSubscriber;

  /**
   * Drupal\webprofiler\Form\FormBuilderWrapper definition.
   *
   * @var \Drupal\webprofiler\Form\FormBuilderWrapper
   */
  protected $formBuilder;

  /**
   * Constructs a new NewsSearchForm object.
   */
  public function __construct(
  ClientInterface $http_client, Connection $database, EventSubscriberInterface $ajax_response_subscriber, FormBuilderWrapper $form_builder
  ) {
    $this->httpClient = $http_client;
    $this->database = $database;
    $this->ajaxResponseSubscriber = $ajax_response_subscriber;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('http_client'), $container->get('database'), $container->get('ajax_response.subscriber'), $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'news_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search_keywords'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Search Keywords'),
        '#description' => $this->t('Enter keywords to search for the news across the glob'),
        '#maxlength' => 50,
        '#size' => 64,
        '#attributes' => ['autocomplete' => 'off'],
        '#weight' => '0',
        '#ajax' => [
            'callback' => '::searchCallback',
            'wrapper' => 'search-result-container',
            'event' => 'change'
        ]
    ];

    $form['submit'] = [
        '#type' => 'submit',
        '#value' => "Search",
        '#attributes' => ['style' => ['display:none;']],
        '#ajax' => [
            'callback' => '::searchCallback',
            'wrapper' => 'search-result-container',
        ]
    ];

    $form['search_resut'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'search-result-container'],
    ];

    if ($form_state->getValue('search_keywords', NULL) != "") {
      
      $result = $this->serve($form_state->getValue('search_keywords'));
      $resultHTML = $this->renderHtml($result);
      $page = pager_default_initialize($result->totalResults, 20);
      
      $pager = [
        '#type' => 'pager',
        '#ajax' => [
            'callback' => '::searchCallback',
            'wrapper' => 'search-result-container',
            'event' => 'click'
        ]
      ];
      
      $pagerHtml = \Drupal::service('renderer')->render($pager);
      
      
      $form['search_resut']['#markup'] = $resultHTML . '<br/>'. $pagerHtml . '<br/>' . $result->totalResults;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return $form['search_resut'];
  }

  public function searchCallback($form, FormStateInterface $form_state) {
    return $form['search_resut'];
  }

  private function serve($query) {
    $service = \Drupal::service('news.service');

    $result = $service
        ->setEndpoint('everything')
        ->setQuery($query)
        ->execute();
    if ($result) {
      return $result;
    }
  }
  
  private function renderHtml($result){
    return twig_render_template(drupal_get_path('module', 'newsapi') . '/templates/search-result.html.twig', (array) $result);
  }


}
