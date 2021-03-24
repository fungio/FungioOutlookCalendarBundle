<?php

namespace Fungio\OutlookCalendarBundle\Service;

use DateTime;

/**
 * Class OutlookCalendar
 * @package Fungio\OutlookCalendarBundle\Service
 *
 * @author  Pierrick AUBIN <fungio76@gmail.com>
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
    protected $authority = 'https://login.microsoftonline.com';

    /**
     * @var string
     */
    protected $authorizeUrl = '/common/oauth2/v2.0/authorize?client_id=%1$s&redirect_uri=%2$s&state=%3$s&scope=%4$s&response_type=code';

    /**
     * @var string
     */
    protected $tokenUrl = '/common/oauth2/v2.0/token';

    /**
     * @var string
     */
    protected $logoutUrl = '/common/oauth2/logout?post_logout_redirect_uri=%1$s';

    /**
     * @var string
     */
    protected $graphApiUrl = 'https://graph.microsoft.com/v1.0';

    /**
     * @var string
     */
    protected $scopes = '';

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * construct
     */
    public function __construct()
    {
        $this->scopes = implode(
            ' ', [
                'openid',
                'https://graph.microsoft.com/Calendars.ReadWrite',
                'https://graph.microsoft.com/User.ReadBasic.All',
                'offline_access'
            ]
        );
    }

    /**
     * @param $scope
     */
    public function addScope($scope)
    {
        $this->scopes .= ' ' . $scope;
    }

    /**
     * @param $scope
     */
    public function removeScope($scope)
    {
        $scopes = explode(' ', $this->scopes);
        if (($key = array_search($scope, $scopes)) !== false) {
            unset($scopes[$key]);
        }
        $this->scopes = implode(' ', $scopes);
    }

    /**
     * Add contact scope
     */
    public function addScopeContact()
    {
        $this->addScope('https://graph.microsoft.com/Contacts.Read');
    }

    /**
     * Remove contact scope
     */
    public function removeScopeContact()
    {
        $this->removeScope('https://graph.microsoft.com/Contacts.Read');
    }

    /**
     * Add calendar scope
     */
    public function addScopeCalendar()
    {
        $this->addScope('https://graph.microsoft.com/Calendars.ReadWrite');
    }

    /**
     * Remove calendar scope
     */
    public function removeScopeCalendar()
    {
        $this->removeScope('https://graph.microsoft.com/Calendars.ReadWrite');
    }

    /**
     * Add userinfo scope
     */
    public function addScopeUserInfos()
    {
        $this->addScope('https://graph.microsoft.com/User.ReadBasic.All');
    }

    /**
     * Remove userinfo scope
     */
    public function removeScopeUserInfos()
    {
        $this->removeScope('https://graph.microsoft.com/User.ReadBasic.All');
    }

    /**
     * Remove offline_access scope
     */
    public function removeScopeOfflineAccess()
    {
        $this->removeScope('offline_access');
    }

    /**
     * Add offline_access scope
     */
    public function addScopeOfflineAccess()
    {
        $this->removeScope('offline_access');
    }

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
     *
     * @return string
     */
    public static function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    /**
     * @param $inputStr
     *
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
     *
     * @return string
     */
    public function getLoginUrl($redirectUri)
    {
        return $this->authority . sprintf($this->authorizeUrl, $this->clientId, urlencode($redirectUri), $this->base64UrlEncode(json_encode($this->parameters)), urlencode($this->scopes));
    }

    /**
     * Builds a logout URL based on the redirect URI.
     *
     * @param $redirectUri
     *
     * @return string
     */
    public function getLogoutUrl($redirectUri)
    {
        return $this->authority . sprintf($this->logoutUrl, urlencode($redirectUri));
    }

    /**
     * Sends a request to the token endpoint to exchange an auth code
     * for an access token.
     *
     * @param $authCode
     * @param $redirectUri
     *
     * @return array|mixed
     */
    public function getTokenFromAuthCode($authCode, $redirectUri)
    {
        // Build the form data to post to the OAuth2 token endpoint
        $token_request_data = [
            'grant_type'    => 'authorization_code',
            'code'          => $authCode,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->scopes
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
            curl_setopt($curl, CURLOPT_PROXY, '127.0.0.1:8888');
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($this->isFailure($httpCode)) {
            return [
                'errorNumber' => $httpCode,
                'error'       => 'Token request returned HTTP error ' . $httpCode
            ];
        }

        // Check error
        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);
        if ($curl_errno) {
            $msg = $curl_errno . ': ' . $curl_err;

            return [
                'errorNumber' => $curl_errno,
                'error'       => $msg
            ];
        }

        curl_close($curl);
        // The response is a JSON payload, so decode it into
        // an array.
        return json_decode($response, true);
    }

    /**
     * Sends a request to the token endpoint to get a new access token
     * from a refresh token.
     *
     * @param $refreshToken
     * @param $redirectUri
     *
     * @return array|mixed
     */
    public function getTokenFromRefreshToken($refreshToken, $redirectUri)
    {
        // Build the form data to post to the OAuth2 token endpoint
        $token_request_data = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'redirect_uri'  => $redirectUri,
            'scope'         => $this->scopes,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret
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
            curl_setopt($curl, CURLOPT_PROXY, '127.0.0.1:8888');
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($this->isFailure($httpCode)) {
            return [
                'errorNumber' => $httpCode,
                'error'       => 'Token request returned HTTP error ' . $httpCode
            ];
        }

        // Check error
        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);
        if ($curl_errno) {
            $msg = $curl_errno . ': ' . $curl_err;

            return [
                'errorNumber' => $curl_errno,
                'error'       => $msg
            ];
        }

        curl_close($curl);

        // The response is a JSON payload, so decode it into
        // an array.
        return json_decode($response, true);
    }

    /**
     * Uses the Calendar API's CalendarView to get all events
     * on a specific day. CalendarView handles expansion of recurring items.
     *
     * @param           $access_token
     * @param DateTime  $date
     * @param           $calendarId
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getEventsForDate($access_token, DateTime $date, $calendarId = null)
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
        $calendarViewUrl = $this->graphApiUrl . '/me/' . ($calendarId !== null ? 'calendars/' . $calendarId . '/' : '') . 'calendarview?'
            . 'startDateTime=' . $windowStartUrl
            . '&endDateTime=' . $windowEndUrl
            . '&$select=Subject,Start,End,Location';

        return $this->makeApiCall($access_token, 'GET', $calendarViewUrl);
    }

    /**
     * Uses the Calendar API's CalendarView to get all events
     * on a specific day. CalendarView handles expansion of recurring items.
     *
     * @param           $access_token
     * @param DateTime  $start
     * @param DateTime  $end
     * @param           $calendarId
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getEventsOnRange($access_token, DateTime $start, DateTime $end, $calendarId = null)
    {
        // Set the start of our view window to midnight of the specified day.
        $windowStart = $start;
        $windowStartUrl = $windowStart->format('Y-m-d\TH:i:s');

        // Add one day to the window start time to get the window end.
        $windowEnd = $end;
        $windowEndUrl = $windowEnd->format('Y-m-d\TH:i:s');

        // Build the API request URL
        $calendarViewUrl = $this->graphApiUrl . '/me/' . ($calendarId !== null ? 'calendars/' . $calendarId . '/' : '') . 'calendarview?'
            . 'startDateTime=' . $windowStartUrl
            . '&endDateTime=' . $windowEndUrl
            . '&$select=Subject,Start,End,Location';

        return $this->makeApiCall($access_token, 'GET', $calendarViewUrl);
    }

    /**
     * @param $access_token
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getUserInfos($access_token)
    {
        return $this->makeApiCall($access_token, 'GET', $this->graphApiUrl . '/me');
    }

    /**
     * @param $access_token
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getPhoto($access_token)
    {
        return $this->makeApiCall($access_token, 'GET', $this->graphApiUrl . '/me/photo');
    }

    /**
     * @param $access_token
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function getCalendars($access_token)
    {
        return $this->makeApiCall($access_token, 'GET', $this->graphApiUrl . '/me/calendars');
    }

    /**
     * @param string   $access_token
     * @param string   $subject
     * @param string   $content
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @param string   $attendeeString
     * @param string   $location
     * @param boolean  $allDay
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function addEventToCalendar($access_token, $subject, $content, DateTime $startTime, DateTime $endTime, $attendeeString = '', $location = '', $allDay = false)
    {
        $startTime->setTimeZone(new \DateTimeZone('UTC'));
        $endTime->setTimeZone(new \DateTimeZone('UTC'));
        if ($allDay) {
            $startTime = clone $startTime;
            $endTime->setTime(0, 0, 0);

            $endTime = clone $startTime;
            $endTime->modify('+1 day');
        }

        $tz = $startTime->getTimezone();
        // Generate the JSON payload
        $event = [
            'subject' => $subject,
            'start'   => [
                'dateTime' => $startTime->format('Y-m-d\TH:i:s\Z'),
                'timeZone' => $tz->getName()
            ],
            'end'     => [
                'dateTime' => $endTime->format('Y-m-d\TH:i:s\Z'),
                'timeZone' => $tz->getName()
            ],
            'body'    => [
                'contentType' => 'HTML',
                'content'     => $content
            ]
        ];
        if ($location != '') {
            $event['location'] = [
                'displayName' => $location
            ];
        }

        $attendeeAddresses = $attendeeString;
        if (!is_array($attendeeAddresses)) {
            $attendeeAddresses = array_filter(explode(';', $attendeeString));
        }
        if (count($attendeeAddresses)) {
            $attendees = [];
            foreach ($attendeeAddresses as $address) {
                if ($address != '') {
                    $attendee = [
                        'emailAddress' => [
                            'address' => $address
                        ],
                        'type'         => 'required'
                    ];

                    $attendees[] = $attendee;
                }
            }

            $event['attendees'] = $attendees;
        }

        $eventPayload = json_encode($event);

        $createEventUrl = $this->graphApiUrl . '/me/events';

        $response = $this->makeApiCall($access_token, 'POST', $createEventUrl, $eventPayload);

        // If the call succeeded, the response should be a JSON representation of the
        // new event. Try getting the Id property and return it.
        if (isset($response['id'])) {
            return $response['id'];
        }

        return $response;
    }

    /**
     * @param           $access_token
     * @param           $eventId
     * @param           $subject
     * @param           $content
     * @param DateTime  $startTime
     * @param DateTime  $endTime
     * @param string    $attendeeString
     * @param string    $location
     * @param bool      $allDay
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function updateEvent($access_token, $eventId, $subject, $content, DateTime $startTime, DateTime $endTime, $attendeeString = '', $location = '', $allDay = false)
    {
        $startTime->setTimeZone(new \DateTimeZone('UTC'));
        $endTime->setTimeZone(new \DateTimeZone('UTC'));
        if ($allDay) {
            $startTime = clone $startTime;
            $endTime->setTime(0, 0, 0);

            $endTime = clone $startTime;
            $endTime->modify('+1 day');
        }

        $tz = $startTime->getTimezone();
        // Generate the JSON payload
        $event = [
            'subject' => $subject,
            'start'   => [
                'dateTime' => $startTime->format('Y-m-d\TH:i:s\Z'),
                'timeZone' => $tz->getName()
            ],
            'end'     => [
                'dateTime' => $endTime->format('Y-m-d\TH:i:s\Z'),
                'timeZone' => $tz->getName()
            ],
            'body'    => [
                'contentType' => 'HTML',
                'content'     => $content
            ]
        ];
        if ($location != '') {
            $event['location'] = [
                'displayName' => $location
            ];
        }

        $attendeeAddresses = $attendeeString;
        if (!is_array($attendeeAddresses)) {
            $attendeeAddresses = array_filter(explode(';', $attendeeString));
        }
        if (count($attendeeAddresses)) {
            $attendees = [];
            foreach ($attendeeAddresses as $address) {
                if ($address != '') {
                    $attendee = [
                        'emailAddress' => [
                            'address' => $address
                        ],
                        'type'         => 'required'
                    ];

                    $attendees[] = $attendee;
                }
            }

            $event['attendees'] = $attendees;
        }

        $eventPayload = json_encode($event);


        $calendarViewUrl = $this->graphApiUrl . '/me/events/' . $eventId;

        $response = $this->makeApiCall($access_token, 'PATCH', $calendarViewUrl, $eventPayload);

        // If the call succeeded, the response should be a JSON representation of the
        // new event. Try getting the Id property and return it.
        if (isset($response['id'])) {
            return $response['id'];
        }

        return $response;
    }

    /**
     * @param $access_token
     * @param $eventId
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function deleteEvent($access_token, $eventId)
    {
        $calendarViewUrl = $this->graphApiUrl . '/me/events/' . $eventId;
        $response = $this->makeApiCall($access_token, 'DELETE', $calendarViewUrl);

        // If the call succeeded, the response should be a JSON representation of the
        // new event. Try getting the Id property and return it.
        if (isset($response['id'])) {
            return $response['id'];
        }

        return $response;
    }

    /**
     * @param $access_token
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function listContacts($access_token)
    {
        // Build the API request URL
        $calendarViewUrl = $this->graphApiUrl . '/me/contacts?'
            . '$select=EmailAddresses,GivenName,Surname';

        $contacts = $this->makeApiCall($access_token, 'GET', $calendarViewUrl);
        $results = [];
        foreach ($contacts['value'] as $contact) {
            $name = '';
            if (isset($contact['givenName']) && !empty($contact['givenName'])) {
                $name .= ' ' . $contact['givenName'];
            }
            if (isset($contact['surname']) && !empty($contact['surname'])) {
                $name .= ' ' . $contact['surname'];
            }

            $results[] = [
                'firstname' => $contact['givenName'],
                'lastname'  => $contact['surname'],
                'name'      => trim($name),
                'email'     => $contact['emailAddresses'][0]['address']
            ];
        }

        return $results;
    }

    /**
     * Make an API call.
     *
     * @param      $access_token
     * @param      $method
     * @param      $url
     * @param null $payload
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function makeApiCall($access_token, $method, $url, $payload = null)
    {
        // Generate the list of headers to always send.
        $headers = [
            'User-Agent: php-tutorial/1.0',
            // Sending a User-Agent header is a best practice.
            'Authorization: Bearer ' . $access_token,
            // Always need our auth token!
            'Accept: application/json',
            // Always accept JSON response.
            'client-request-id: ' . $this->makeGuid(),
            // Stamp each new request with a new GUID.
            'return-client-request-id: true',
            // Tell the server to include our request-id GUID in the response
        ];

        $curl = curl_init($url);

        switch (strtoupper($method)) {
            case 'GET':
                // Nothing to do, GET is the default and needs no
                // extra headers.
                break;
            case 'POST':
                // Add a Content-Type header (IMPORTANT!)
                $headers[] = 'Content-Type: application/json';
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;
            case 'PATCH':
                // Add a Content-Type header (IMPORTANT!)
                $headers[] = 'Content-Type: application/json';
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                throw new \RuntimeException('INVALID METHOD: ' . $method);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            return [
                'errorNumber' => $httpCode,
                'error'       => 'Request returned HTTP error ' . $httpCode
            ];
        }

        $curl_errno = curl_errno($curl);
        $curl_err = curl_error($curl);

        if ($curl_errno) {
            $msg = $curl_errno . ': ' . $curl_err;
            curl_close($curl);

            return [
                'errorNumber' => $curl_errno,
                'error'       => $msg
            ];
        }

        curl_close($curl);

        return json_decode($response, true);
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
        }

        $charid = strtolower(md5(uniqid(mt_rand(), true)));
        $hyphen = chr(45);
        return substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
    }

    /**
     * @param $access_token
     *
     * @return bool
     */
    public function isConnected($access_token)
    {
        $events = $this->getEventsForDate($access_token, new DateTime('now'));
        if (array_key_exists('error', $events)) {
            return false;
        }

        return true;
    }

    /**
     * @param $httpStatus
     *
     * @return bool
     */
    public function isFailure($httpStatus)
    {
        // Simplistic check for failure HTTP status
        return ($httpStatus >= 400);
    }

    /**
     * @return string
     */
    public function getGraphApiUrl()
    {
        return $this->graphApiUrl;
    }
}
