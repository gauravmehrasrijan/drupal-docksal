<?php

namespace Drupal\newsapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\webprofiler\Form\FormBuilderWrapper;

/**
 * Class NewsConfigForm.
 */
class NewsConfigForm extends ConfigFormBase {

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
   * Drupal\webprofiler\Form\FormBuilderWrapper definition.
   *
   * @var \Drupal\webprofiler\Form\FormBuilderWrapper
   */
  protected $formBuilder;

  /**
   * Constructs a new NewsConfigForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    Connection $database,
    FormBuilderWrapper $form_builder
  ) {
    parent::__construct($config_factory);
    $this->httpClient = $http_client;
    $this->database = $database;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('database'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'newsapi.newsconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'news_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('newsapi.newsconfig');
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API KEY'),
      '#description' => $this->t('Please provide your API_KEY recieved from newapi.org while registration.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('api_key'),
    ];
    
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News API uri'),
      '#description' => $this->t('Newsapi base url'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('api_url'),
    ];
    
    $form['news_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News Limit'),
      '#description' => $this->t('Limit your news display at the front screen'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('news_limit'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('newsapi.newsconfig')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('news_limit', $form_state->getValue('news_limit'))
      ->set('api_url', $form_state->getValue('api_url'))
      ->save();
  }

}
