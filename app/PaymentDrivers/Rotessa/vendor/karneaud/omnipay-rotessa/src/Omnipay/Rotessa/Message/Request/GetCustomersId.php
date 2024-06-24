<?php
namespace Omnipay\Rotessa\Message\Request;
// You will need to create this BaseRequest class as abstracted from the AbstractRequest; 
use Omnipay\Rotessa\Message\Request\BaseRequest;
use Omnipay\Rotessa\Message\Request\RequestInterface;

class GetCustomersId extends BaseRequest implements RequestInterface
{
  
  protected $endpoint = '/customers/{id}';
  protected $method = 'GET';
  protected static $model = '';


  
  public function setId(int $value) {
    $this->setParameter('id',$value);  
  }
}
