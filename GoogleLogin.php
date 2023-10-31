<?php
declare(strict_types=1);

use SKien\Google\GClient;
use SKien\Google\GContacts;
use SKien\Google\GSecrets;

require_once 'autoloader.php';

/**
 * Call the google Oauth2 authentication.
 * This call first shows the google login form before the consent of the user
 * to grant access to the requested resource(s) (-> the 'scope') is asked.
 *
 * If access is granted, a auth code to get required tokens is passed to the
 * configured redirect URI.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
$oSecrets = new GSecrets(-1, GSecrets::TOKEN_FILE);
$oClient = new GClient();
$oClient->setOAuthClient($oSecrets->getClientSecrets());
$oClient->addScope(GContacts::CONTACTS);
$oClient->addScope(GContacts::CONTACTS_OTHER_READONLY);

$strAuthURL = $oClient->buildAuthURL();

header('Location: ' . filter_var($strAuthURL, FILTER_SANITIZE_URL));