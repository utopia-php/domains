<?php

namespace Utopia\Domains\Registrar;

use Utopia\Domains\Adapter as DomainsAdapter;

abstract class Adapter extends DomainsAdapter
{
  abstract public function available(string $domain);
  
  abstract public function purchase(string $domain, array $details);
  
  abstract public function suggest(array $query, array $tlds = array(),  $minLength = 1, $maxLength = 100);
  
  abstract public function tlds():array;
  
  abstract public function domain(string $domain);
    
  abstract public function renew(string $domain, int $years);
  
  abstract public function transfer(string $domain, array $details);
}
