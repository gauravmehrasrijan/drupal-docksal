<?php

namespace Drupal\newsapi\Services;

/**
 * Interface NewSerchApiInterface.
 */
interface NewsSerchApiInterface {
    
    public function setEndpoint($enpoint);
    
    public function setQuery($query);
    
    public function execute();

}
