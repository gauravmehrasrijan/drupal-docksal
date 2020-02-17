<?php

namespace Drupal\semantic_connector\Api;

use Drupal\Component\Serialization\Json;

/**
 * Class SemanticConnectorPPTApi_7_1
 *
 * API Class for the version 7.1
 */
class SemanticConnectorPPTApi_7_1 extends SemanticConnectorPPTApi_7_0 {
  /**
   * Export data of a project as a file and store it on the server.
   *
   * @param string $project_id
   *   The ID of the PoolParty project to export and store.
   * @param string $format
   *   The returned RDF format.
   *   Possible values are: TriG, N3, Turtle, N-Triples, RDF/XML, TriX
   * @param string $export_modules
   *   A list of the export modules for the data that should be exported.
   *   Possible values are:
   *   - concepts - includes concept schemes, concepts, collections and notes
   *   - workflow - workflow status for all concepts
   *   - history - all history events
   *   - freeConcepts - all free concepts
   *   - void - the project VoiD graph
   *   - adms - the project ADMS graph
   *
   * @return string
   *   The URL of the stored file or an empty string if an error occurred.
   */
  public function storeProject($project_id, $format = 'RDF/XML', $export_modules = 'concepts') {
    $resource_path = $this->getApiPath() . 'projects/' . $project_id . '/store';
    $post_parameters = array(
      'format' => $format,
      'exportModules' => $export_modules,
      'prettyPrint' => TRUE,
    );
    $file_path = $this->connection->post($resource_path, array(
      'data' => $post_parameters,
    ));
    $file_path = Json::decode($file_path);

    return (filter_var($file_path, FILTER_VALIDATE_URL) !== FALSE) ? $file_path : '';
  }
}
