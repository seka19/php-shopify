<?php
/**
 * Created by PhpStorm.
 * @author Tareq Mahmood <tareqtms@yahoo.com>
 * Created at 8/17/16 2:50 PM UTC+06:00
 */

namespace PHPShopify;


use PHPShopify\Exception\CurlException;
use PHPShopify\Exception\ResourceRateLimitException;

/*
|--------------------------------------------------------------------------
| CurlRequest
|--------------------------------------------------------------------------
|
| This class handles get, post, put, delete HTTP requests
|
*/
class CurlRequest
{
    /**
     * HTTP Code of the last executed request
     *
     * @var integer
     */
    public static $lastHttpCode;


    /**
     * Initialize the curl resource
     *
     * @param string $url
     * @param array $httpHeaders
     *
     * @return resource
     */
    protected static function init($url, $httpHeaders = array())
    {
        // Create Curl resource
        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $url);

        //Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPClassic/PHPShopify');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $headers = array();
        foreach ($httpHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }
        //Set HTTP Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;

    }

    /**
     * Implement a GET request and return output
     *
     * @param string $url
     * @param array $httpHeaders
     *
     * @return CurlResponse
     *
     * @throws CurlException
     * @throws ResourceRateLimitException
     */
    public static function get($url, $httpHeaders = array())
    {
        //Initialize the Curl resource
        $ch = self::init($url, $httpHeaders);

        return self::processRequest($ch);
    }

    /**
     * Implement a POST request and return output
     *
     * @param string $url
     * @param array $data
     * @param array $httpHeaders
     *
     * @return CurlResponse
     *
     * @throws CurlException
     * @throws ResourceRateLimitException
     */
    public static function post($url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //Set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return self::processRequest($ch);
    }

    /**
     * Implement a PUT request and return output
     *
     * @param string $url
     * @param array $data
     * @param array $httpHeaders
     *
     * @return CurlResponse
     *
     * @throws CurlException
     * @throws ResourceRateLimitException
     */
    public static function put($url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return self::processRequest($ch);
    }

    /**
     * Implement a DELETE request and return output
     *
     * @param string $url
     * @param array $httpHeaders
     *
     * @return CurlResponse
     *
     * @throws CurlException
     * @throws ResourceRateLimitException
     */
    public static function delete($url, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return self::processRequest($ch);
    }

    /**
     * Execute a request, release the resource and return output
     *
     * @param resource $ch
     *
     * @throws CurlException if curl request is failed with error
     * @throws ResourceRateLimitException
     *
     * @return CurlResponse
     */
    protected static function processRequest($ch)
    {
        $err500tries = 30;
        while (1) {
            $output   = curl_exec($ch);
            $response = new CurlResponse($output);

            self::$lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (self::$lastHttpCode === 429) {
                # Check for 429 leaky bucket error
                $limitHeader = explode('/', $response->getHeader('X-Shopify-Shop-Api-Call-Limit'), 2);
                if (isset($limitHeader[1]) && $limitHeader[0] < $limitHeader[1]) {
                    throw new ResourceRateLimitException($response->getBody());
                }
                usleep(500000);
                continue;
            }

            if ((int)floor(self::$lastHttpCode / 100) === 5 && $err500tries) {
                # Retry on 50x error
                sleep(10);
                $err500tries--;
                continue;
            }

            break;
        }

        if (curl_errno($ch)) {
            throw new Exception\CurlException(curl_errno($ch) . ' : ' . curl_error($ch));
        }

        // close curl resource to free up system resources
        curl_close($ch);

        //return $response->getBody();
        return $response;
    }

}
