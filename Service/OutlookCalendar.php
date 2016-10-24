<?php

namespace Fungio\OutlookCalendarBundle\Service;

/**
 * Class OutlookCalendar
 * @package Fungio\OutlookCalendarBundle\Service
 *
 * @author Pierrick AUBIN <fungio76@gmail.com>
 */
class OutlookCalendar
{
    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $authority = "https://login.microsoftonline.com";

    /**
     * @var string
     */
    protected $authorizeUrl = '/common/oauth2/v2.0/authorize?client_id=%1$s&redirect_uri=%2$s&state=%3$s&scope=%4$s&response_type=code';

    /**
     * @var string
     */
    protected $tokenUrl = "/common/oauth2/v2.0/token";

    /**
     * @var string
     */
    protected $logoutUrl = '/common/oauth2/logout?post_logout_redirect_uri=%1$s';

    /**
     * @var string
     */
    protected $outlookApiUrl = "https://outlook.office.com/api/v2.0";

    /**
     * @var string
     */
    protected $scopes = "openid https://outlook.office.com/calendars.readwrite offline_access";

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * Set this to true to enable Fiddler capture.
     * Note that if you have this set to true and you are not running Fiddler
     * on the web server, requests will silently fail.
     *
     * @var bool
     */
    protected $enableFiddler = false;

    /**
     * @param string $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * @param string $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @param $inputStr
     * @return string
     */
    public static function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    /**
     * @param $inputStr
     * @return string
     */
    public static function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }

    /**
     * Builds a login URL based on the client ID and redirect URI
     *
     * @param $redirectUri
     * @return string
     */
    public function getLoginUrl($redirectUri)
    {
        $loginUrl = $this->authority . sprintf($this->authorizeUrl, $this->clientId, urlencode($redirectUri), $this->base64UrlEncode(json_encode($this->parameters)), urlencode($this->scopes));
        return $loginUrl;
    }

    /**
     * Builds a logout URL based on the redirect URI.
     *
     * @param $redirectUri
     * @return string
     */
    public function getLogoutUrl($redirectUri)
    {
        $logoutUrl = $this->authority . sprintf($this->logoutUrl, urlencode($redirectUri));
        return $logoutUrl;
    }

    /**
     * Sends a request to the token endpoint to exchange an auth code
     * for an access token.
     *
     * @param $authCode
     * @param $redirectUri
     * @return array|mixed
     */
    public function getTokenFromAuthCode($authCode, $redirectUri)
    {
        // Build the form data to post to the OAuth2 token endpoint
        $token_request_data = [
            "grant_type" => "authorization_code",
            "code" => $authCode,
            "redirect_uri" => $redirectUri,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
            "scope" => $this->scopes
        ];

        // Calling http_build_query is important to get the data
        // formatted as Azure expects.
        $token_request_body = http_build_query($token_request_data);

        $curl = curl_init($this->authority . $this->tokenUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $token_request_body);

        if ($this->enableFiddler) {
            // ENABLE FIDDLER TRACE
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            // SET PROXY TO FIDDLER PROXY
            curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1:8888");
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($this->isFailure($httpCode)) {
            return [
                'errorNumber' => $httpCode,
                'error' => 'Token request returned HTTP error ' . $httpCode
            ];
        }

        // Check error
        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);
        if ($curl_errno) {
            $msg = $curl_errno . ": " . $curl_err;
            return [
                'errorNumber' => $curl_errno,
                'error' => $msg
            ];
        }

        curl_close($curl);
        // The response is a JSON payload, so decode it into
        // an array.
        $json_vals = json_decode($response, true);
        return $json_vals;
    }

    /**
     * Sends a request to the token endpoint to get a new access token
     * from a refresh token.
     *
     * @param $refreshToken
     * @param $redirectUri
     * @return array|mixed
     */
    public function getTokenFromRefreshToken($refreshToken, $redirectUri)
    {
        // Build the form data to post to the OAuth2 token endpoint
        $token_request_data = [
            "grant_type" => "refresh_token",
            "refresh_token" => $refreshToken,
            "redirect_uri" => $redirectUri,
            "scope" => $this->scopes,
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret
        ];

        $token_request_body = http_build_query($token_request_data);

        $curl = curl_init($this->authority . $this->tokenUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $token_request_body);

        if ($this->enableFiddler) {
            // ENABLE FIDDLER TRACE
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            // SET PROXY TO FIDDLER PROXY
            curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1:8888");
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($this->isFailure($httpCode)) {
            return [
                'errorNumber' => $httpCode,
                'error' => 'Token request returned HTTP error ' . $httpCode
            ];
        }

        // Check error
        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);
        if ($curl_errno) {
            $msg = $curl_errno . ": " . $curl_err;
            return [
                'errorNumber' => $curl_errno,
                'error' => $msg
            ];
        }

        curl_close($curl);

        // The response is a JSON payload, so decode it into
        // an array.
        $json_vals = json_decode($response, true);

        return $json_vals;
    }

    /**
     * @param $access_token
     * @param $eventId
     * @param $params
     * @return array|mixed
     * @throws \Exception
     */
    public function updateEvent($access_token, $eventId, $params)
    {
        $calendarViewUrl = $this->outlookApiUrl . "/me/events/" . $eventId;

        return $this->makeApiCall($access_token, "PATCH", $calendarViewUrl, $params);
    }

    /**
     * Uses the Calendar API's CalendarView to get all events
     * on a specific day. CalendarView handles expansion of recurring items.
     *
     * @param $access_token
     * @param \DateTime $date
     * @return array|mixed
     */
    public function getEventsForDate($access_token, \DateTime $date)
    {
        // Set the start of our view window to midnight of the specified day.
        $windowStart = clone $date;
        $windowStart->setTime(0, 0, 0);
        $windowStartUrl = $windowStart->format('Y-m-d\TH:i:s');

        // Add one day to the window start time to get the window end.
        $windowEnd = clone $windowStart;
        $windowEnd->setTime(23, 59, 59);
        $windowEndUrl = $windowEnd->format('Y-m-d\TH:i:s');

        // Build the API request URL
        $calendarViewUrl = $this->outlookApiUrl . "/me/calendarview?"
            . "startDateTime=" . $windowStartUrl
            . "&endDateTime=" . $windowEndUrl
            . '&$select=Subject,Start,End,Location';

        return $this->makeApiCall($access_token, "GET", $calendarViewUrl);
    }

    /**
     * Make an API call.
     *
     * @param $access_token
     * @param $method
     * @param $url
     * @param null $payload
     * @return array|mixed
     * @throws \Exception
     */
    public function makeApiCall($access_token, $method, $url, $payload = NULL)
    {
        // Generate the list of headers to always send.
        $headers = [
            "User-Agent: php-tutorial/1.0",         // Sending a User-Agent header is a best practice.
            "Authorization: Bearer " . $access_token, // Always need our auth token!
            "Accept: application/json",             // Always accept JSON response.
            "client-request-id: " . $this->makeGuid(), // Stamp each new request with a new GUID.
            "return-client-request-id: true",       // Tell the server to include our request-id GUID in the response
        ];

        $curl = curl_init($url);

        switch (strtoupper($method)) {
            case "GET":
                // Nothing to do, GET is the default and needs no
                // extra headers.
                break;
            case "POST":
                // Add a Content-Type header (IMPORTANT!)
                $headers[] = "Content-Type: application/json";
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;
            case "PATCH":
                // Add a Content-Type header (IMPORTANT!)
                $headers[] = "Content-Type: application/json";
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;
            case "DELETE":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                throw new \Exception("INVALID METHOD: " . $method);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            return [
                'errorNumber' => $httpCode,
                'error' => 'Request returned HTTP error ' . $httpCode
            ];
        }

        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);

        if ($curl_errno) {
            $msg = $curl_errno . ": " . $curl_err;
            curl_close($curl);
            return [
                'errorNumber' => $curl_errno,
                'error' => $msg
            ];
        } else {
            curl_close($curl);
            return json_decode($response, true);
        }
    }

    /**
     * This function generates a random GUID.
     *
     * @return string
     */
    public function makeGuid()
    {
        if (function_exists('com_create_guid')) {
            return strtolower(trim(com_create_guid(), '{}'));
        } else {
            $charid = strtolower(md5(uniqid(rand(), true)));
            $hyphen = chr(45);
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);

            return $uuid;
        }
    }

    /**
     * @param $access_token
     * @return bool
     */
    public function isConnected($access_token)
    {
        $events = $this->getEventsForDate($access_token, new \DateTime('now'));
        if (array_key_exists('error', $events)) {
            return false;
        }
        return true;
    }

    /**
     * @param $httpStatus
     * @return bool
     */
    public function isFailure($httpStatus)
    {
        // Simplistic check for failure HTTP status
        return ($httpStatus >= 400);
    }
}