<?php

namespace SofWar\Instagram;

/**
 * Instagram API class
 *
 * API Documentation: http://instagram.com/developer/
 * Class Documentation: https://github.com/sofwar/Instagram-PHP-API
 *
 * @author SofWar
 * @since 19.09.2016
 * @version 1.0
 * @license BSD http://www.opensource.org/licenses/bsd-license.php
 */
class Instagram
{
    /**
     * The API base URL.
     */
    const API_URL = 'https://api.instagram.com/v1/';

    /**
     * The API OAuth URL.
     */
    const API_OAUTH_URL = 'https://api.instagram.com/oauth/authorize';

    /**
     * The OAuth token URL.
     */
    const API_OAUTH_TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

    /**
     * The Instagram API Key.
     *
     * @var string
     */
    private $_apikey;

    /**
     * The Instagram OAuth API secret.
     *
     * @var string
     */
    private $_apisecret;

    /**
     * The callback URL.
     *
     * @var string
     */
    private $_callbackurl;

    /**
     * The user access token.
     *
     * @var string
     */
    private $_accesstoken;

    /**
     * Whether a signed header should be used.
     *
     * @var bool
     */
    private $_signedheader = false;

    /**
     * Available scopes.
     *
     * @var string[]
     */
    private $_scopes = ['basic', 'likes', 'comments', 'relationships', 'public_content', 'follower_list'];

    /**
     * Available actions.
     *
     * @var string[]
     */
    private $_actions = ['follow', 'unfollow', 'approve', 'ignore'];

    /**
     * Rate limit.
     *
     * @var int
     */
    private $_xRateLimitRemaining;

    /**
     * Response code
     *
     * @var int
     */
    private $_code = 0;

    /**
     * Response error message
     *
     * @var string
     */
    private $_error_message;

    /**
     * Response error type
     *
     * @var string
     */
    private $_error_type;

    /**
     * Default constructor.
     *
     * @param array|string $config Instagram configuration data
     *
     * @return void
     *
     * @throws \SofWar\Instagram\InstagramException
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            // if you want to access user data
            $this->setApiKey($config['apiKey']);
            $this->setApiSecret($config['apiSecret']);
            $this->setApiCallback($config['apiCallback']);
        } elseif (is_string($config)) {
            // if you only want to access public data
            $this->setApiKey($config);
        } else {
            throw new InstagramException('Error: __construct() - Configuration data is missing.');
        }
    }

    /**
     * Generates the OAuth login URL.
     *
     * @param string[] $scopes Requesting additional permissions
     *
     * @return string Instagram OAuth login URL
     *
     * @throws \SofWar\Instagram\InstagramException
     */
    public function getLoginUrl($scopes = ['basic'])
    {
        if (is_array($scopes) && count(array_intersect($scopes, $this->_scopes)) === count($scopes)) {
            return self::API_OAUTH_URL . '?client_id=' . $this->getApiKey() . '&redirect_uri=' . urlencode($this->getApiCallback()) . '&scope=' . implode('+',
                $scopes) . '&response_type=code';
        }

        throw new InstagramException("Error: getLoginUrl() - The parameter isn't an array or invalid scope permissions used.");
    }

    /**
     * Search for a user.
     *
     * @param string $name Instagram username
     * @param int $limit Limit of returned results
     *
     * @return array
     */
    public function searchUser($name, $limit = 100)
    {
        $params = [
            'q' => $name,
            'limit' => $limit
        ];

        return $this->_makeCall('users/search', $params);
    }

    /**
     * Get user info.
     *
     * @param int $id Instagram user ID
     *
     * @return array
     */
    public function getUser($id = 0)
    {
        return $this->_makeCall('users/' . ($id ?: 'self'));
    }

    /**
     * Get user recent media.
     *
     * @param int $id Instagram user ID
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserMedia($id = 0, $limit = 100)
    {
        $params = [
            'count' => $limit
        ];

        return $this->_makeCall('users/' . ($id ?: 'self') . '/media/recent', $params);
    }

    /**
     * Get the liked photos of a user.
     *
     * @param int $limit Limit of returned results
     * @param string $max_like_id Return media liked before
     *
     * @return mixed
     */
    public function getUserLikes($limit = 100, $max_like_id = null)
    {
        $params = [
            'count' => $limit,
            'max_like_id' => $max_like_id
        ];

        return $this->_makeCall('users/self/media/liked', $params);
    }

    /**
     * Get the list of users this user follows
     *
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserFollows($limit = 100)
    {
        $params = [
            'count' => $limit
        ];

        return $this->_makeCall('users/self/follows', $params);
    }

    /**
     * Get the list of users this user is followed by.
     *
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserFollower($limit = 100)
    {
        $params = [
            'count' => $limit
        ];

        return $this->_makeCall('users/self/followed-by', $params);
    }

    /**
     * Get the list of users who have requested this user's permission to follow.
     *
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function getUserRequest($limit = 100)
    {
        $params = [
            'count' => $limit
        ];

        return $this->_makeCall('users/self/requested-by', $params);
    }

    /**
     * Get information about a relationship to another user.
     *
     * @param int $id Instagram user ID
     *
     * @return mixed
     */
    public function getUserRelationship($id = 0)
    {
        return $this->_makeCall('users/' . $id . '/relationship');
    }

    /**
     * Get the value of X-RateLimit-Remaining header field.
     *
     * @return int X-RateLimit-Remaining API calls left within 1 hour
     */
    public function getRateLimit()
    {
        return $this->_xRateLimitRemaining;
    }

    /**
     * Modify the relationship between the current user and the target user.
     *
     * @param string $action Action command (follow/unfollow/approve/ignore)
     * @param int $id Target user ID
     *
     * @return mixed
     *
     * @throws \SofWar\Instagram\InstagramException
     */
    public function modifyRelationship($action, $id)
    {
        if (in_array($action, $this->_actions, null)) {
            $params = [
                'action' => $action
            ];

            return $this->_makeCall('users/' . $id . '/relationship', $params, 'POST');
        }

        throw new InstagramException('Error: modifyRelationship() | This method requires an action command and the target user id.');
    }

    /**
     * Search media by its location.
     *
     * @param float $lat Latitude of the center search coordinate
     * @param float $lng Longitude of the center search coordinate
     * @param int $distance Distance in metres (default is 1km (distance=1000), max. is 5km)
     *
     * @return mixed
     */

    public function searchMedia($lat, $lng, $distance = 1000)
    {
        $params = [
            'lat' => $lat,
            'lng' => $lng,
            'distance' => $distance
        ];

        return $this->_makeCall('media/search', $params);
    }

    /**
     * Get media by its id.
     *
     * @param string $id Instagram media ID
     *
     * @return mixed
     */
    public function getMedia($id)
    {
        return $this->_makeCall('media/' . $id);
    }

    /**
     * This endpoint returns the same response.
     *
     * A media object's shortcode can be found in its shortlink URL. An example shortlink is
     * http://instagram.com/p/tsxp1hhQTG/. Its corresponding shortcode is tsxp1hhQTG.
     *
     * @param string $code Shortcode
     *
     * @return mixed
     */
    public function getMediaShort($code)
    {
        return $this->_makeCall('media/shortcode/' . $code);
    }

    /**
     * Search for tags by name.
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function searchTags($name)
    {
        $params = [
            'q' => $name
        ];

        return $this->_makeCall('tags/search', $params);
    }

    /**
     * Get info about a tag
     *
     * @param string $name Valid tag name
     *
     * @return mixed
     */
    public function getTag($name)
    {
        return $this->_makeCall('tags/' . $name);
    }

    /**
     * Get a recently tagged media.
     *
     * @param string $name Valid tag name
     * @param int $limit Limit of returned results
     * @param string $min_tag_id Return media before
     * @param string $max_tag_id Return media after
     *
     * @return array
     */

    public function getTagMedia($name, $limit = 100, $min_tag_id = null, $max_tag_id = null)
    {
        $params = [
            'count' => $limit,
            'min_tag_id' => $min_tag_id,
            'max_tag_id' => $max_tag_id
        ];

        return $this->_makeCall('tags/' . $name . '/media/recent', $params);
    }

    /**
     * Get a list of users who have liked this media.
     *
     * @param string $id Instagram media ID
     *
     * @return mixed
     */

    public function getMediaLikes($id)
    {
        return $this->_makeCall('media/' . $id . '/likes');
    }

    /**
     * Get a list of comments for this media.
     *
     * @param string $id Instagram media ID
     *
     * @return mixed
     */
    public function getMediaComments($id)
    {
        return $this->_makeCall('media/' . $id . '/comments');
    }

    /**
     * Add a comment on a media.
     *
     * @param int $id Instagram media ID
     * @param string $text Comment content
     *
     * @return mixed
     */
    public function addMediaComment($id, $text)
    {
        $params = [
            'text' => $text
        ];

        return $this->_makeCall('media/' . $id . '/comments', $params, 'POST');
    }

    /**
     * Remove user comment on a media.
     *
     * @param int $id Instagram media ID
     * @param string $commentID User comment ID
     *
     * @return mixed
     */
    public function deleteMediaComment($id, $commentID)
    {
        return $this->_makeCall('media/' . $id . '/comments/' . $commentID, null, 'DELETE');
    }

    /**
     * Set user like on a media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function likeMedia($id)
    {
        return $this->_makeCall('media/' . $id . '/likes', null, 'POST');
    }

    /**
     * Remove user like on a media.
     *
     * @param int $id Instagram media ID
     *
     * @return mixed
     */
    public function deleteLikedMedia($id)
    {
        return $this->_makeCall('media/' . $id . '/likes', null, 'DELETE');
    }

    /**
     * Get information about a location.
     *
     * @param int $id Instagram location ID
     *
     * @return mixed
     */
    public function getLocation($id)
    {
        return $this->_makeCall('locations/' . $id);
    }

    /**
     * Get recent media from a given location.
     *
     * @param int $id Instagram location ID
     *
     * @return mixed
     */
    public function getLocationMedia($id)
    {
        return $this->_makeCall('locations/' . $id . '/media/recent');
    }

    /**
     * Get recent media from a given location.
     *
     * @param float $lat Latitude of the center search coordinate
     * @param float $lng Longitude of the center search coordinate
     * @param string $fb_places_id Returns a location mapped off of a Facebook places id. If used, lat and lng are not required.
     * @param int $distance Distance in meter (max. distance: 5km = 5000)
     *
     * @return mixed
     */
    public function searchLocation($lat, $lng, $fb_places_id = null, $distance = 1000)
    {
        $params = [
            'lat' => $lat,
            'lng' => $lng,
            'facebook_places_id' => $fb_places_id,
            'distance' => $distance
        ];

        return $this->_makeCall('locations/search', $params);
    }

    /**
     * Given a short link, returns the embed code and information about the media associated with that link.
     * @param string $q Short link or short code
     * @return mixed
     */
    public function getOembed($q)
    {
        if (strpos($q, 'http') === false) {
            $q = 'https://www.instagram.com/p/' . $q;
        }

        $data = json_decode(file_get_contents('https://api.instagram.com/oembed/?hidecaption=true&url=' . $q), true);

        return $data;
    }

    /**
     * Return media ID
     *
     * @param string $q Short link or short code
     * @return mixed
     */
    public function getMediaId($q)
    {
        $data = $this->getOembed($q);

        if (isset($data['media_id'])) {
            return $data['media_id'];
        }

        return null;
    }

    /**
     * Pagination feature.
     *
     * @param object $obj Instagram object returned by a method
     * @param int $limit Limit of returned results
     *
     * @return mixed
     */
    public function pagination($obj, $limit = 0)
    {
        if (is_object($obj) && !is_null($obj->pagination)) {
            if (!isset($obj->pagination->next_url)) {
                return false;
            }

            $apiCall = explode('?', $obj->pagination->next_url);

            if (count($apiCall) < 2) {
                return false;
            }

            $function = str_replace(self::API_URL, '', $apiCall[0]);

            if (isset($obj->pagination->next_max_id)) {
                return $this->_makeCall($function, ['max_id' => $obj->pagination->next_max_id, 'count' => $limit]);
            }

            return $this->_makeCall($function, ['cursor' => $obj->pagination->next_cursor, 'count' => $limit]);
        }

        return null;
    }

    /**
     * Get the OAuth data of a user by the returned callback code.
     *
     * @param string $code OAuth2 code variable (after a successful login)
     * @param bool $token If it's true, only the access token will be returned
     *
     * @return mixed
     */
    public function getOAuthToken($code, $token = false)
    {
        $apiData = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->getApiKey(),
            'client_secret' => $this->getApiSecret(),
            'redirect_uri' => $this->getApiCallback(),
            'code' => $code
        );

        $result = $this->_makeOAuthCall($apiData);

        return !$token ? $result : $result->access_token;
    }

    /**
     * The call operator.
     *
     * @param string $function API resource path
     * @param array $params Additional request parameters
     * @param string $method Request type GET|POST
     *
     * @return mixed
     *
     * @throws \SofWar\Instagram\InstagramException
     */
    protected function _makeCall($function, $params = null, $method = 'GET')
    {
        // if the call needs an authenticated user
        if (!isset($this->_accesstoken)) {
            throw new InstagramException("Error: _makeCall() | $function - This method requires an authenticated users access token.");
        }

        $authMethod = '?access_token=' . $this->getAccessToken() . '&client_id=' . $this->getApiKey();

        $paramString = null;

        if (isset($params) && is_array($params)) {
            $paramString = '&' . http_build_query($params);
        }

        $apiCall = self::API_URL . $function . $authMethod . (($method === 'GET') ? $paramString : null);

        // we want JSON
        $headerData = array('Accept: application/json');

        if ($this->_signedheader) {
            $apiCall .= (strstr($apiCall, '?') ? '&' : '?') . 'sig=' . $this->_signHeader($function, $authMethod, $params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiCall);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, count($params));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);

        // split header from JSON data
        // and assign each to a variable
        list($headerContent, $response) = explode("\r\n\r\n", $response, 2);

        // convert header content into an array
        $headers = $this->processHeaders($headerContent);

        // get the 'X-Ratelimit-Remaining' header value
        $this->_xRateLimitRemaining = $headers['X-Ratelimit-Remaining'];

        if (!$response) {
            throw new InstagramException('Error: _makeCall() - cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $jsonData = json_decode($response);

        if (!is_null($jsonData)) {
            if (array_key_exists('meta', $jsonData)) {
                $this->_code = $jsonData->meta->code;
                $this->_error_message = null;
                $this->_error_type = null;

                return $jsonData;
            } else if (array_key_exists('code', $jsonData)) {
                $this->_code = $jsonData->code;
                $this->_error_message = $jsonData->error_message;
                $this->_error_type = $jsonData->error_type;
            }
        }

        throw new InstagramException('Error: _makeCall() - cURL error: ' . curl_error($ch));
    }

    /**
     * The OAuth call operator.
     *
     * @param array $apiData The post API data
     *
     * @return mixed
     *
     * @throws \SofWar\Instagram\InstagramException
     */
    private function _makeOAuthCall($apiData)
    {
        $apiHost = self::API_OAUTH_TOKEN_URL;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiHost);
        curl_setopt($ch, CURLOPT_POST, count($apiData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        $jsonData = curl_exec($ch);

        if (!$jsonData) {
            throw new InstagramException('Error: _makeOAuthCall() - cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($jsonData);
    }

    /**
     * Sign header by using endpoint, parameters and the API secret.
     *
     * @param string
     * @param string
     * @param array
     *
     * @return string The signature
     */
    private function _signHeader($endpoint, $authMethod, $params)
    {
        if (!is_array($params)) {
            $params = [];
        }

        if ($authMethod) {
            list($key, $value) = explode('=', substr($authMethod, 1), 2);
            $params[$key] = $value;
        }

        $baseString = '/' . $endpoint;

        ksort($params);

        foreach ($params as $key => $value) {
            $baseString .= '|' . $key . '=' . $value;
        }

        return hash_hmac('sha256', $baseString, $this->_apisecret, false);
    }

    /**
     * Read and process response header content.
     *
     * @param array
     *
     * @return array
     */
    private function processHeaders($headerContent)
    {
        $headers = [];

        foreach (explode("\r\n", $headerContent) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
                continue;
            }

            list($key, $value) = explode(':', $line);
            $headers[$key] = $value;
        }

        return $headers;
    }

    /**
     * Access Token Setter.
     *
     * @param object|string $data
     *
     * @return void
     */
    public function setAccessToken($data)
    {
        $token = is_object($data) ? $data->access_token : $data;

        $this->_accesstoken = $token;
    }

    /**
     * Access Token Getter.
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->_accesstoken;
    }

    /**
     * API-key Setter
     *
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->_apikey = $apiKey;
    }

    /**
     * API Key Getter
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->_apikey;
    }

    /**
     * API Secret Setter
     *
     * @param string $apiSecret
     *
     * @return void
     */
    public function setApiSecret($apiSecret)
    {
        $this->_apisecret = $apiSecret;
    }

    /**
     * API Secret Getter.
     *
     * @return string
     */
    public function getApiSecret()
    {
        return $this->_apisecret;
    }

    /**
     * API Callback URL Setter.
     *
     * @param string $apiCallback
     *
     * @return void
     */
    public function setApiCallback($apiCallback)
    {
        $this->_callbackurl = $apiCallback;
    }

    /**
     * API Callback URL Getter.
     *
     * @return string
     */
    public function getApiCallback()
    {
        return $this->_callbackurl;
    }

    /**
     * Enforce Signed Header.
     *
     * @param bool $signedHeader
     *
     * @return void
     */
    public function setSignedHeader($signedHeader)
    {
        $this->_signedheader = $signedHeader;
    }

    /**
     * Request status code
     * @return int
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Request error message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->_error_message;
    }

    /**
     * Request error type
     *
     * @return string
     */
    public function getErrorType()
    {
        return $this->_error_type;
    }
}
