<?php

namespace Drupal\Tests\search_api\Functional;

use Drupal\block\Entity\Block;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Task;

/**
 * Tests the cacheability metadata of Search API.
 *
 * @group search_api
 */
class CacheabilityTest extends SearchApiBrowserTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'rest',
    'search_api',
    'search_api_test',
    'search_api_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Set up example structure and content and populate the test index with
    // that content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll(Index::load($this->indexId));
    $this->indexItems($this->indexId);
  }

  /**
   * Tests the cacheability settings of Search API.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    // Verify that the search results are marked as uncacheable.
    $this->drupalGet('search-api-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('x-drupal-dynamic-cache', 'UNCACHEABLE');
    $this->assertContains('no-cache', $this->drupalGetHeader('cache-control'));

    // Verify that the search results are displayed.
    $this->assertSession()->pageTextContains('foo test');
    $this->assertSession()->pageTextContains('foo baz');
  }

  /**
   * Tests the cache metadata of the "Execute pending tasks" action.
   */
  public function testExecuteTasksAction() {
    // Enable the "Local actions" block so we can verify which local actions are
    // displayed.
    try {
      $success = $this->container->get('module_installer')->install(['block'], TRUE);
      $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => 'block']));
    }
    catch (MissingDependencyException $e) {
      // The exception message has all the details.
      $this->fail($e->getMessage());
    }
    Block::create([
      'id' => 'classy_local_actions',
      'theme' => 'classy',
      'weight' => -20,
      'plugin' => 'local_actions_block',
      'region' => 'content',
    ])->save();

    $assert_session = $this->assertSession();
    $admin_path = 'admin/config/search/search-api';

    $this->drupalLogin($this->adminUser);

    // At first, the action should not be present.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');

    // Create one task.
    $task = Task::create([

    ]);
    $task->save();

    // Now the action should be shown.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Execute pending tasks');

    // Delete the task again.
    $task->delete();

    // Now the action should be hidden again.
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
    $this->drupalGet($admin_path);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Execute pending tasks');
  }

  /**
   * Tests that indexing or deleting items clears the cache.
   */
  public function testViewsCacheAddRemoveContent() {
    $entity = $this->addTestEntity(6, [
      'name' => 'Fresh node',
      'body' => 'test foobar Case',
      'type' => 'item',
    ]);
    // Prime page cache before indexing.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 5 search results');

    $this->indexItems($this->indexId);

    // Check that the newly indexed node is visible on the search index.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 6 search results');

    $entity->delete();

    // Check that the deleted entity is now no longer shown.
    $this->drupalGet('search-api-test-search-view-caching-tag');
    $this->assertSession()->pageTextContains('Displaying 5 search results');
  }

}
