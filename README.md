# ![Image](example/assets/instagram.png) Instagram PHP API V1

A PHP wrapper for the Instagram API. Feedback or bug reports are appreciated.

> [Composer](#installation) package available.  

## Requirements

- PHP 5.4 or higher
- cURL
- Registered Instagram App

## Get started

To use the Instagram API you have to register yourself as a developer at the [Instagram Developer Platform](http://instagr.am/developer/register/) and create an application. Take a look at the [uri guidelines](#samples-for-redirect-urls) before registering a redirect URI. You will receive your `client_id` and `client_secret`.

---

Please note that Instagram mainly refers to »Clients« instead of »Apps«. So »Client ID« and »Client Secret« are the same as »App Key« and »App Secret«.

---

### Installation

I strongly advice using [Composer](https://getcomposer.org) to keep updates as smooth as possible.

```
$ composer require cosenary/instagram
```

### Initialize the class

```php
use SofWar\Instagram\Instagram;

$instagram = new Instagram(array(
	'apiKey'      => 'YOUR_APP_KEY',
	'apiSecret'   => 'YOUR_APP_SECRET',
	'apiCallback' => 'YOUR_APP_CALLBACK'
));

echo "<a href='{$instagram->getLoginUrl()}'>Login with Instagram</a>";
```

### Authenticate user (OAuth2)

```php
// grab OAuth callback code
$code = $_GET['code'];
$data = $instagram->getOAuthToken($code);

echo 'Your username is: ' . $data->user->username;
```

### Get user likes

```php
// set user access token
$instagram->setAccessToken($data);

// get all user likes
$likes = $instagram->getUserLikes();

// take a look at the API response
echo '<pre>';
print_r($likes);
echo '<pre>';
```

**All methods return the API data `json_decode()` - so you can directly access the data.**

## Available methods

### Setup Instagram

`new Instagram(<array>/<string>);`

`array` if you want to authenticate a user and access its data:

```php
new Instagram(array(
	'apiKey'      => 'YOUR_APP_KEY',
	'apiSecret'   => 'YOUR_APP_SECRET',
	'apiCallback' => 'YOUR_APP_CALLBACK'
));
```

`string` if you *only* want to access public data:

```php
new Instagram('YOUR_APP_KEY');
```

### Get login URL

`getLoginUrl(<array>)`

```php
getLoginUrl(array(
	'basic',
	'likes'
));
```

### Get OAuth token

`getOAuthToken($code, <boolean>)`

`true` : Returns only the OAuth token
`false` *[default]* : Returns OAuth token and profile data of the authenticated user

### Set / Get access token

- Set the access token, for further method calls: `setAccessToken($token)`
- Get the access token, if you want to store it for later usage: `getAccessToken()`

### User methods

- `getUser(<$id>)`
- `searchUser($name, <$limit>)`
- `getUserMedia($id, <$limit>)`
- `getUserLikes(<$limit>, <$max_like_id>)`
- `getUserMedia(<$id>, <$limit>)`
	- if an `$id` isn't defined or equals `'self'`, it returns the media of the logged in user

> [Sample responses of the User Endpoints.](http://instagram.com/developer/endpoints/users/)

### Relationship methods

- `getUserFollows(<$limit>)`
- `getUserFollower(<$limit>)`
- `getUserRelationship($id)`
- `modifyRelationship($action, $user)`
	- `$action` : Action command (follow / unfollow / approve / ignore)
	- `$user` : Target user id

```php
// Follow the user with the ID 1521204717
$instagram->modifyRelationship('follow', 1521204717);
```

---

Please note that the `modifyRelationship()` method requires the `relationships` [scope](#get-login-url).

---

> [Sample responses of the Relationship Endpoints.](http://instagram.com/developer/endpoints/relationships/)

### Media methods

- `getMedia($id)`
- `getMediaShort($code)`
- `searchMedia($lat, $lng, <$distance>)`

> [Sample responses of the Media Endpoints.](http://instagram.com/developer/endpoints/media/)

### Comment methods

- `getMediaComments($id)`
- `addMediaComment($id, $text)`
- `deleteMediaComment($id, $commentID)`

---

Please note that the authenticated methods require the `comments` [scope](#get-login-url).

---

> [Sample responses of the Comment Endpoints.](http://instagram.com/developer/endpoints/comments/)

### Tag methods

- `getTag($name)`
- `getTagMedia($name, <$limit>, <$min_tag_id>, <$max_tag_id>)`
- `searchTags($name)`

> [Sample responses of the Tag Endpoints.](http://instagram.com/developer/endpoints/tags/)

### Likes methods

**Authenticated methods**

- `getMediaLikes($id)`
- `likeMedia($id)`
- `deleteLikedMedia($id)`

> [Sample responses of the Likes Endpoints.](http://instagram.com/developer/endpoints/likes/)

All `<...>` parameters are optional. If the limit is undefined, all available results will be returned.

---

## Signed Header

In order to prevent that your access tokens gets stolen, Instagram recommends to sign your requests with a hash of your API secret, the called endpoint and parameters.

1. Activate ["Enforce Signed Header"](http://instagram.com/developer/clients/manage/) in your Instagram client settings.
2. Enable the signed-header in your Instagram class:

```php
$instagram->setSignedHeader(true);
```

3. You are good to go! Now, all your requests will be secured with a signed header.

Go into more detail about how it works in the [Instagram API Docs](http://instagram.com/developer/restrict-api-requests/#enforce-signed-header).

## Pagination

Each endpoint has a maximum range of results, so increasing the `limit` parameter above the limit won't help (e.g. `getUserMedia()` has a limit of 90).

That's the point where the "pagination" feature comes into play.
Simply pass an object into the `pagination()` method and receive your next dataset:

```php
$photos = $instagram->getTagMedia('kitten');

$result = $instagram->pagination($photos);
```

Iteration with `do-while` loop.

## Samples for redirect URLs

<table>
	<tr>
		<th>Registered Redirect URI</th>
		<th>Redirect URI sent to /authorize</th>
		<th>Valid?</th>
	</tr>
	<tr>
		<td>http://yourcallback.com/</td>
		<td>http://yourcallback.com/</td>
		<td>yes</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/</td>
		<td>http://yourcallback.com/?this=that</td>
		<td>yes</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/?this=that</td>
		<td>http://yourcallback.com/</td>
		<td>no</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/?this=that</td>
		<td>http://yourcallback.com/?this=that&another=true</td>
		<td>yes</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/?this=that</td>
		<td>http://yourcallback.com/?another=true&this=that</td>
		<td>no</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/callback</td>
		<td>http://yourcallback.com/</td>
		<td>no</td>
	</tr>
	<tr>
		<td>http://yourcallback.com/callback</td>
		<td>http://yourcallback.com/callback/?type=mobile</td>
		<td>yes</td>
	</tr>
</table>

> If you need further information about an endpoint, take a look at the [Instagram API docs](http://instagram.com/developer/authentication/).

## Changelog

Please see the [changelog file](CHANGELOG.md) for more information.

Released under the [BSD License](LICENSE).
