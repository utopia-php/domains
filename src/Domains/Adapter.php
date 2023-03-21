<?php

namespace Utopia\Domains;

abstract class Adapter
{
    protected string $userAgent = 'Utopia PHP Framework';

    protected string $endpoint;

    protected string $apiKey;

    protected string $apiSecret;

    protected $headers = [
        'Content-Type' => 'application/json',
    ];

    /**
     * __construct
     * Instantiate a new adapter.
     *
     * @param  string  $env
     */
    public function __construct(string $endpoint, string $apiKey, string $apiSecret)
    {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        $this->headers = [
            'Authorization' => 'sso-key '.$this->apiKey.':'.$this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

      /**
       * Call
       *
       * Make an API call
       *
       * @param  array  $params
       *
       * @throws \Exception
       */
      public function call(string $method, string $path = '', array|string $params = [], array $headers = []): array|string
      {
          $headers = array_merge($this->headers, $headers);
          $ch = curl_init(
              (
                  str_contains($path, 'http')
                  ? $path
                  : $this->endpoint.$path.(
                      ($method == 'GET' && ! empty($params) && $headers['Content-Type'] != 'text/xml')
                      ? '?'.http_build_query($params)
                      : ''
                  )
              )
          );

          $responseHeaders = [];
          $responseStatus = -1;
          $responseType = '';
          $responseBody = '';

          $query = null;

          if (! empty($params)) {
              switch ($headers['Content-Type']) {
                  case 'application/json':
                      $query = json_encode($params, JSON_UNESCAPED_SLASHES);
                      break;

                  case 'multipart/form-data':
                      $query = $this->flatten($params);
                      break;

                  case 'text/xml':
                      $query = $params;
                      break;

                  default:
                      $query = http_build_query($params);
                      break;
              }
          }

          foreach ($headers as $i => $header) {
              $headers[] = $i.':'.$header;

              unset($headers[$i]);
          }

          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_USERAGENT, php_uname('s').'-'.php_uname('r').':php-'.phpversion());
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
              $len = strlen($header);
              $header = explode(':', strtolower($header), 2);

              if (count($header) < 2) { // ignore invalid headers
                  return $len;
              }

              $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);

              return $len;
          });

          if ($method != 'GET') {
              curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
          }

          $responseBody = curl_exec($ch);

          $responseType = $responseHeaders['Content-Type'] ?? '';
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

          if ($responseStatus >= 400) {
              if (is_array($responseBody)) {
                  throw new \Exception(json_encode($responseBody));
              } else {
                  throw new \Exception($responseStatus.': '.$responseBody);
              }
          }

          return $responseBody;
      }

      /**
       * Flatten params array to PHP multiple format
       */
      protected function flatten(array $data, string $prefix = ''): array
      {
          $output = [];

          foreach ($data as $key => $value) {
              $finalKey = $prefix ? "{$prefix}[{$key}]" : $key;

              if (is_array($value)) {
                  $output += $this->flatten($value, $finalKey); // @todo: handle name collision here if needed
              } else {
                  $output[$finalKey] = $value;
              }
          }

          return $output;
      }
}
