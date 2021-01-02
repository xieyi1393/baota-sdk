<?php


namespace BTSDK\Connections;

use BTSDK\Interfaces\ServerConnection;
use BTSDK\Transmission\APIRequest;
use BTSDK\Transmission\APIResponse;
use BTSDK\Exceptions\CredentialError;
use BTSDK\Exceptions\NetworkException;
use BTSDK\Exceptions\NotSupportException;
use BTSDK\Exceptions\ResponseDecodeException;
use BTSDK\Exceptions\ServerException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;

class GuzzleServerConnection extends BaseServerConnection implements ServerConnection
{
    protected $serverAddress;
    protected $httpClient;
    public function __construct($serverAddress,$timeout=2.0){
            $this->serverAddress=$serverAddress;
            $this->httpClient=new Client(
                [
                    'base_uri'=>$this->serverAddress,
                    'timeout'=>$timeout,
                    'allow_redirects'=>false
                ]
            );
    }
    protected function buildPostBodyData(array $array){
        $postData="";
        foreach($array as $k=>$v){
            if(!empty($postData))$postData.="&";
            $postData.=$k."=".urlencode($v);
        }
        return $postData;
    }
    public function sendRequest(APIRequest $request)
    {
        $guzzleHttpRequest=new Request(
            $request->method,
            $request->url,
            $request->headers,
            $this->buildPostBodyData($request->body)
        );
        //var_dump($this->buildPostBodyData($request->body));
        try{
            $guzzleHttpResponse=$this->httpClient->send($guzzleHttpRequest);
        }catch (TransferException $e){
            throw new NetworkException("GuzzleHttpError:".$e->getMessage());
        }
        if($guzzleHttpResponse->getStatusCode()==302 && substr($guzzleHttpResponse->getHeaderLine("Location"),-5)=="login"){
                throw new CredentialError();
        }
        if($guzzleHttpResponse->getStatusCode()==404){
            throw new NotSupportException();
        }
        if($guzzleHttpResponse->getStatusCode()!=200){
            throw new ServerException($guzzleHttpResponse->getStatusCode());
        }
        $response=new APIResponse();
        $guzzleHttpResponse->getBody();
        $responseJson=json_decode($guzzleHttpResponse->getBody()->getContents(),true);
        if(json_last_error()!=JSON_ERROR_NONE){
            throw new ResponseDecodeException();
        }
        $response->fill($responseJson);
        return $response;
    }
}