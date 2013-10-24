<?php

namespace PiwikClientTest;

require_once 'PHPUnit/Autoload.php';

use PiwikClient\PiwikClient,
    \PHPUnit_Framework_TestCase,
    Buzz\Client\Curl,
    Buzz\Browser;

class PiwikClientTest extends PHPUnit_Framework_TestCase
{
    protected static $apiUrl = 'http://piwik.org';
    protected static $apiToken = 'anonymous';
    /**
     * Default test parameters. If changed, also need to change asserts conditions.
     * @var mixed
     */
    protected static $defaultTestParams = array(
        'siteName' => 'Example site 1',
        'urls[]' => array('http://example.com', 'https://www.example.com'),
        'ecommerce' => true,
        'siteSearch' => 1,
        'searchKeywordParameters' => array(
            'q','query','s','search','searchword','k','keyword'
        ),
        'searchCategoryParameters' => array(
            'category 1','category 2',
        ),
        'excludedIps' => array(
            '127.0.*.*','192.168.*.*',
        ),
        'excludedQueryParameters' => array(
            'token','sessid',
        ),
        'timezone' => 'UTC+3',
        'currency' => 'EUR',
        'group' => 'The group name',
        'startDate' => '2013-01-01 10:00:00',
        'excludedUserAgents' => array(),
        'keepURLFragments' => '1'
    );

    /**
     * Default Piwik client
     * @var PiwikClient\PiwikClient
     */
    protected $client;
    
    public function getClient() {
        if (!$this->client){
            $curl = new Curl();
            $curl->setTimeout(60);
            $this->client = new PiwikClient(self::$apiUrl, self::$apiToken, new Browser($curl));
        }
        return $this->client;
    }

    protected function setUp()
    {
        if (is_file(__DIR__ . '/config.php')) {
            $config = require_once __DIR__ . '/config.php';
        } else {
            $config = require_once __DIR__ . '/config.php.dist';
        }
        if(is_array($config)){
            self::$apiUrl = $config['apiUrl'];
            self::$apiToken = $config['apiToken'];
        }
    }

    public function testConstructor()
    {
        $this->assertInstanceOf('PiwikClient\PiwikClient', new PiwikClient(self::$apiUrl, self::$apiToken));
    }

    public function testHttpClientDefault()
    {
        $piwik = new PiwikClient(self::$apiUrl, self::$apiToken);
        $this->assertInstanceOf('Buzz\Browser', $piwik->getHttpClient());
    }

    public function boolParamsProvider()
    {
        return array(
            array(
                array(
                    'ecommerce' => 1,
                    'siteSearch' => 0,
                    'keepURLFragments' => 0,
                )
            ),
            array(
                array(
                    'ecommerce' => true,
                    'siteSearch' => false,
                    'keepURLFragments' => false,
                )
            ),
            array(
                array(
                    'ecommerce' => '1',
                    'siteSearch' => '0',
                    'keepURLFragments' => '0',
                )
            ),
            array(
                array(
                    'ecommerce' => true,
                    'siteSearch' => '0',
                    'keepURLFragments' => 0,
                )
            ),
        );
    }

    /**
     * @dataProvider boolParamsProvider
     */
    public function testBoolParamsRequestUrl($params)
    {
        $this->assertEquals('ecommerce=1&siteSearch=0&keepURLFragments=0', $this->getClient()->buildRequestUrlQuery($params));
    }

    public function testArrayParamsRequestUrl()
    {
        $params = array(
            'searchKeywordParameters' => array('q', 'query', 's', 'search', 'searchword', 'k', 'keyword'),
            'searchCategoryParameters' => array('category 1', 'category 2'),
            'excludedIps' => array('127.0.*.*', '192.168.*.*'),
            'excludedQueryParameters' => array('token', 'sess_id'),
        );
        
        $this->assertEquals('searchKeywordParameters=q,query,s,search,searchword,k,keyword&searchCategoryParameters=category+1,category+2&excludedIps=127.0.%2A.%2A,192.168.%2A.%2A&excludedQueryParameters=token,sess_id', $this->getClient()->buildRequestUrlQuery($params));
    }

    public function testBracketedArrayParamRequestUrl()
    {
        $params = array('urls[]' => array('http://example.com', 'https://www.example.com'));
        $this->assertEquals('urls[0]=http%3A%2F%2Fexample.com&urls[1]=https%3A%2F%2Fwww.example.com', $this->getClient()->buildRequestUrlQuery($params));
    }

    public function urlsProvider()
    {
        $urls = array();

        $params = array_merge_recursive(array(
            'module' => 'API',
            'method' => 'SitesManager.addSite',
            'token_auth' => self::$apiToken,
            'format' => 'json',
        ), self::$defaultTestParams);
        $urls[] = array(self::$apiUrl . '?' . $this->getClient()->buildRequestUrlQuery($params));
        return $urls;
    }

    /**
     * @dataProvider urlsProvider
     */
    public function testUrlValidness($url)
    {
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== FALSE);
    }

    public function testRequestUrl()
    {
        $params = array_merge_recursive(array(
            'module' => 'API',
            'method' => 'SitesManager.addSite',
            'token_auth' => self::$apiToken,
            'format' => 'json',
        ), self::$defaultTestParams);
        
        $this->assertEquals('module=API&method=SitesManager.addSite&token_auth=' . self::$apiToken . '&format=json&siteName=Example+site+1&urls[0]=http%3A%2F%2Fexample.com&urls[1]=https%3A%2F%2Fwww.example.com&ecommerce=1&siteSearch=1&searchKeywordParameters=q,query,s,search,searchword,k,keyword&searchCategoryParameters=category+1,category+2&excludedIps=127.0.%2A.%2A,192.168.%2A.%2A&excludedQueryParameters=token,sessid&timezone=UTC%2B3&currency=EUR&group=The+group+name&startDate=2013-01-01+10%3A00%3A00&excludedUserAgents=&keepURLFragments=1', $this->getClient()->buildRequestUrlQuery($params));
    }

    public function testSiteAddCall()
    {
        $params = self::$defaultTestParams;

        $idSite = $this->getClient()->call('SitesManager.addSite', $params, 'php');
        $this->assertTrue(is_numeric($idSite));

        return $idSite;
    }

    /**
     * @depends testSiteAddCall
     */
    public function testGetAddedSite($idSite)
    {
        $params = self::$defaultTestParams;
        
        $site = $this->getClient()->call('SitesManager.getSiteFromId', array('idSite' => $idSite), 'php');
        
        $this->assertEquals($params['siteName'], $site[0]['name']);
        $this->assertEquals($params['urls[]'][0], $site[0]['main_url']);
        $this->assertEquals($params['startDate'], $site[0]['ts_created']);
        $this->assertEquals(urlencode((int)$params['ecommerce']), $site[0]['ecommerce']);
        $this->assertEquals(urlencode((int)$params['siteSearch']), $site[0]['sitesearch']);
        $this->assertEquals($params['timezone'], $site[0]['timezone']);
        $this->assertEquals($params['currency'], $site[0]['currency']);
        $this->assertEquals(implode(',', $params['searchKeywordParameters']), $site[0]['sitesearch_keyword_parameters']);
        $this->assertEquals(implode(',', $params['searchCategoryParameters']), $site[0]['sitesearch_category_parameters']);
        $this->assertEquals(implode(',', $params['excludedIps']), $site[0]['excluded_ips']);
        $this->assertEquals(implode(',', $params['excludedQueryParameters']), $site[0]['excluded_parameters']);
        $this->assertEquals(implode(',', $params['excludedUserAgents']), $site[0]['excluded_user_agents']);
        $this->assertEquals($params['group'], $site[0]['group']);
        $this->assertEquals(urlencode((int)$params['keepURLFragments']), $site[0]['keep_url_fragment']);

        return $site;
    }

    /**
     * @depends testSiteAddCall
     */
    public function testDeleteAddedSite($idSite)
    {
        $result = $this->getClient()->call('SitesManager.deleteSite', array('idSite' => $idSite), 'php');
        $this->assertEquals('success', $result['result']);
        $this->assertEquals('ok', $result['message']);

        return $result;
    }

}

?>
