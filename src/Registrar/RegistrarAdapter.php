<?php

namespace Utopia\Registrars;

use Exception;

abstract class RegistrarAdapter
{
    protected bool $enabled = true;

    protected string $userAgent = 'Utopia PHP Framework';

    protected string $endpoint;

    protected string $apiKey;
    
    protected string $apiSecret;

    protected string $shopperId;

    protected $headers = [
      'Content-Type' => '',
    ];


    /**
     * Call
     *
     * Make an API call
     *
     * @param string $method
     * @param string $path
     * @param array $params
     * @param array $headers
     * @return array|string
     * @throws \Exception
     */
    public function call(string $method, string $path = '', array|string $params = [], array $headers = array()): array|string
    {
        $headers            = array_merge($this->headers, $headers);
        $ch                 = curl_init((str_contains($path, 'http') ? $path : $this->endpoint . $path . (($method == 'GET' && !empty($params)) ? '?' . http_build_query($params) : '')));
        $responseHeaders    = [];
        $responseStatus     = -1;
        $responseType       = '';
        $responseBody       = '';

        if(!empty($params)) {
          switch ($headers['Content-Type']) {
              case 'application/json':
                  $query = json_encode($params);
                  break;
  
              case 'multipart/form-data':
                  $query = $this->flatten($params);
                  break;
  
              default:
                  $query = http_build_query($params);
                  break;
          }
        }

        foreach ($headers as $i => $header) {
            $headers[] = $i . ':' . $header;
            unset($headers[$i]);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s') . '-' . php_uname('r') . ':php-' . phpversion());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', strtolower($header), 2);

            if (count($header) < 2) { // ignore invalid headers
                return $len;
            }

            $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);

            return $len;
        });

        if($method != 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }

        $responseBody   = curl_exec($ch);

        $responseType   = $responseHeaders['Content-Type'] ?? '';
        $responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        switch(substr($responseType, 0, strpos($responseType, ';'))) {
            case 'application/json':
                $responseBody = json_decode($responseBody, true);
            break;
        }

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        
        curl_close($ch);

        if($responseStatus >= 400) {
            if(is_array($responseBody)) {
                throw new \Exception(json_encode($responseBody));
            } else {
                throw new \Exception($responseStatus . ': ' . $responseBody);
            }
        }


        return $responseBody;
    }

      /**
     * Flatten params array to PHP multiple format
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    protected function flatten(array $data, string $prefix = ''): array {
      $output = [];

      foreach($data as $key => $value) {
          $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

          if (is_array($value)) {
              $output += $this->flatten($value, $finalKey); // @todo: handle name collision here if needed
          }
          else {
              $output[$finalKey] = $value;
          }
      }

      return $output;
  }

  
  abstract public function available(string $domain);
  
  abstract public function purchase(string $domain, array $details);
  
  abstract public function cancelPurchase(string $domain);
  
  abstract public function suggest(array $query, array $tlds = array(),  $minLength = 1, $maxLength = 100);
  
  abstract public function tlds():array;
  
  abstract public function domain(string $domain);
  
  abstract public function updateDomain(string $domain, array $details);
  
  abstract public function updateRecords(string $domain, array $records);
  
  abstract public function replaceRecords(string $domain, array $records);
  
  abstract public function domainRecord(string $domain, string $type, string $name);
  
  abstract public function addDomainRecord(string $domain, string $type, string $name);
  
  abstract public function updateDomainRecord(string $domain, string $type, string $name);
  
  abstract public function deleteDomainRecord(string $domain, string $type, string $name);
  
  abstract public function renew(string $domain, int $years);
  
  abstract public function transfer(string $domain, array $details);
}
