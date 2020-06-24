<?php

namespace Drupal\newsapi\Services;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Config\ConfigManagerInterface;

/**
 * Class NewsApiService.
 */
class NewsApiService implements NewsSerchApiInterface {

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
     * Drupal\Core\Config\ConfigManagerInterface definition.
     *
     * @var \Drupal\Core\Config\ConfigManagerInterface
     */
    protected $configManager;
    
    protected $api_key;
    
    protected $defaults = ['sortBy'=>'publishedAt','size'=>20,'language'=>'en'];
    
    protected $params = [];

    /**
     * Constructs a new NewsApiService object.
     */
    public function __construct(ClientInterface $http_client, Connection $database, ConfigManagerInterface $config_manager) {
        $this->httpClient = $http_client;
        $this->database = $database;
        $this->configManager = $config_manager;
        
        $config = \Drupal::config('newsapi.newsconfig');
        
        $this->defaults['apiKey'] = $config->get('api_key');
        
        $this->base_url = $config->get('api_url');
       
    }
    
    public function setEndpoint($endpoint){
        $this->base_url .= $endpoint;
        return $this;
    }
    
    public function setQuery($query){
        $this->params['q'] = $query;
        return $this;
    }
    
    public function execute(){
        
        return $this->buildParams()->call();
        
         
    }
    
    public function buildParams(){
        $params = array_merge($this->defaults, $this->params);
        $params = http_build_query($params);
        $this->base_url .= "?" . $params;
        
        return $this;
    }
    
    private function call(){
        
        $headers = [
            "Accept:application/json",
            "Accept-Encoding:gzip"
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->base_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
//            CURLOPT_POSTFIELDS => json_encode($this->params),
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return json_decode($response);
        }
    }
    
    

}
