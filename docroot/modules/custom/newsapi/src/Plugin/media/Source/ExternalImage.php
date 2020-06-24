<?php
namespace Drupal\newsapi\Plugin\media\Source;

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\json_field\Plugin\Field\FieldType\JSONItem;


/**
* External image entity media source.
*
* @see \Drupal\file\FileInterface
*
* @MediaSource(
*   id = "external_image",
*   label = @Translation("External Image"),
*   description = @Translation("Use remote images."),
*   allowed_field_types = {"text_long"},
*   thumbnail_alt_metadata_attribute = "alt",
*   default_thumbnail_filename = "no-thumbnail.png"
* )
*/
class ExternalImage extends MediaSourceBase {
 
    public function getMetadataAttributes() {
        return [
          'title' => $this->t('Title'),
          'alt_text' => $this->t('Alternative text'),
          'caption' => $this->t('Caption'),
          'credit' => $this->t('Credit'),
          'id' => $this->t('ID'),
          'uri' => $this->t('URL'),
          'width' => $this->t('Width'),
          'height' => $this->t('Height'),
        ];
    }
    
    public function getMetadata(MediaInterface $media, $attribute_name) {
        // Get the text_long field where the JSON object is stored
        $remote_field = $media->get($this->configuration['source_field']);
        
//        kint($remote_field); exit;
        $json_arr = json_decode(strip_tags($remote_field->value));
        // If the source field is not required, it may be empty.
        if (!$remote_field) {
          return parent::getMetadata($media, $attribute_name);
        }
        switch ($attribute_name) {
          // This is used to set the name of the media entity if the user leaves the field blank.
          case 'default_name':
            return $json_arr->alt;
          // This is used to generate the thumbnail field.
          case 'thumbnail_uri':
            return $json_arr->uri;
          default:
            return $json_arr->$attribute_name ?? parent::getMetadata($media, $attribute_name);
        }
    }

}
