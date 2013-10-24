<?php
/**
 * The PiwikClient class
 *
 * @author 0x00ten
 */
namespace PiwikClient;

use Buzz\Client\Curl,
    Buzz\Browser;

class PiwikClient
{
    const FORMAT_PHP = 'php';
    const FORMAT_XML = 'xml';
    const FORMAT_JSON = 'json';
    const FORMAT_CSV = 'csv';
    const FORMAT_TSV = 'tsv';
    const FORMAT_HTML = 'html';
    const FORMAT_ORIG = 'original';

    protected $httpClient;
    protected $apiUrl;
    protected $apiToken;

    public function getHttpClient()
    {
        if ($this->httpClient === NULL)
            $this->httpClient = new Browser(new Curl());
        
        return $this->httpClient;
    }

    public function getApiToken()
    {
        return $this->apiToken;
    }

    public function buildRequestUrlQuery($params)
    {
        $query = array();

        foreach ($params as $key => $val) {
            if (is_array($val)) {
                $sub = substr($key, -2, 2);
                if ($sub === '[]'){
                    $key = substr($key, 0, strlen($key) - 2);
                    foreach ($val as $k => $v){
                        if (is_numeric($v) || is_string($v)){
                            $query[] = $key . "[$k]" . '=' . urlencode($v);
                        }
                    }
                } else {
                    foreach ($val as &$v) $v = urlencode($v);
                    $val = implode(',', $val);
                }
            } elseif ($val instanceof \DateTime) {
                $val = $val->format('Y-m-d');
            } elseif (is_bool($val)) {
                if ($val) {
                    $val = 1;
                } else {
                    $val = 0;
                }
            } else {
                $val = urlencode($val);
            }
            if (!(is_array($val) && $sub === '[]'))
                $query[] = $key . '=' . $val;
        }
        
        return implode('&', $query);
    }

    public function __construct($apiUrl, $apiToken = 'anonymous', $httpClient = NULL)
    {
        if ($httpClient !== NULL)
            $this->httpClient = $httpClient;

        $this->apiUrl = $apiUrl;
        $this->apiToken = $apiToken;
    }

    public function call($method, array $params = array(), $format = self::FORMAT_PHP)
    {
        $params['module'] = 'API';
        $params['method'] = $method;
        $params['token_auth'] = $this->apiToken;
        $params['format'] = $format;

        $response = $this->getHttpClient()->get($this->apiUrl . '?' . $this->buildRequestUrlQuery($params))->getContent();
        
        if ($format === self::FORMAT_PHP) {
            $object = unserialize($response);

            if (isset($object['result']) && $object['result'] === 'error') {
                throw new \Exception($object['message']);
            }

            return $object;
        } else {
            return $response;
        }
    }
}

?>
