<?php

namespace Drupal\semantic_connector\Api;

/**
 * Class SemanticConnectorPPXApi_7_1
 *
 * API Class for the version 7.1
 */
class SemanticConnectorPPXApi_7_1 extends SemanticConnectorPPXApi_7_0 {
  /**
   * Get a list of of concepts / free terms matching a string.
   *
   * @param string $string
   *   The string to search matching concepts / freeterms for.
   * @param string $language
   *   The iso-code of the text's language.
   * @param string $project_id
   *   The ID of the PoolParty project to use.
   * @param array $parameters
   *   Additional parameters to forward to the API (e.g., projectId).
   *
   * @return array
   *   An array of objects (every object can be an object or a freeterm,
   *   detectable by the tid-property).
   */
  public function suggest($string, $language, $project_id, array $parameters = array()) {
    $suggestion = parent::suggest($string, $language, $project_id, $parameters);
    // prefLabel was replaced by prefLabels including all languages.
    foreach ($suggestion as &$suggest_item) {
      if (isset($suggest_item['prefLabels']) && isset($suggest_item['prefLabels'][$language])) {
        $suggest_item['prefLabel'] = $suggest_item['prefLabels'][$language];
      }
    }

    return $suggestion;
  }

  /**
   * {@inheritdoc}
   */
  public function extractConcepts($data, $language, array $parameters = array(), $data_type = '', $categorize = FALSE) {
    $concepts = parent::extractConcepts($data, $language, $parameters, $data_type, $categorize);

    // prefLabel was replaced by prefLabels including all languages.
    if (is_array($concepts) && isset($concepts['concepts'])) {
      foreach ($concepts['concepts'] as &$concept) {
        if (isset($concept['prefLabels']) && isset($concept['prefLabels'][$language])) {
          $concept['prefLabel'] = $concept['prefLabels'][$language];
        }
      }
    }

    return $concepts;
  }
}
