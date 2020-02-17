<?php

namespace Drupal\semantic_connector\Api;

use Drupal\Component\Serialization\Json;

/**
 * Class SemanticConnectorSonrApi_7_1
 *
 * API Class for the version 7.1
 */
class SemanticConnectorSonrApi_7_1 extends SemanticConnectorSonrApi_7_0 {
  /**
   * Adds a new custom search field for the suggestion call.
   *
   * @param string $label
   *   The label of the custom search field.
   * @param string $field
   *   The name of the custom search field, e.g. 'content_type'.
   * @param string $search_space_id
   *   The ID of the search space to add the custom search field for.
   *
   * @return boolean
   *   TRUE if field is added, otherwise FALSE.
   */
  public function addCustomSearchField($label, $field, $search_space_id = '') {
    $resource_path = '/' . $this->graphSearchPath . '/admin/suggest/add';
    $field = 'dyn_lit_' . str_replace('-', '_', $field);
    $post_parameters = [
      'field' => $field,
      'label' => $label,
    ];
    if (!empty($search_space_id)) {
      $post_parameters['searchSpaceId'] = $search_space_id;
    }

    $result = $this->connection->post($resource_path, [
      'data' => Json::encode($post_parameters),
    ]);

    if ($result !== FALSE) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Deletes a custom search field for the suggestion call.
   *
   * @param string $field
   *   The name of the custom search field, e.g. 'content_type'.
   * @param string $search_space_id
   *   The ID of the search space to delete the custom search field for.
   *
   * @return boolean
   *   TRUE if field is deleted, otherwise FALSE.
   */
  public function deleteCustomSearchField($field, $search_space_id = '') {
    $resource_path = '/' . $this->graphSearchPath . '/admin/suggest/delete';
    $field = 'dyn_lit_' . str_replace('-', '_', $field);
    $post_parameters = [
      'field' => $field,
    ];
    if (!empty($search_space_id)) {
      $post_parameters['searchSpaceId'] = $search_space_id;
    }

    $result = $this->connection->post($resource_path, [
      'data' => Json::encode($post_parameters),
    ]);

    if ($result !== FALSE) {
      return TRUE;
    }

    return FALSE;
  }
}
