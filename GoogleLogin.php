<?php
declare(strict_types=1);

use SKien\Google\GClient;
use SKien\Google\GContacts;
use SKien\Google\GSecrets;

require_once 'autoloader.php';

/**
 * Call the google Oauth2 authentication.
 * Usually this call first shows the google login form before
 * the consent of the user to grant access to the requested
 * resource(s) (-> the 'scope') is asked.
 *
 * If access is granted, a access code to get required tokens
 * is passed to the configured redirect URI.
 *
 * This example is only intended to demonstrate the use of the package. The UI
 * is only coded 'quick and dirty', contains no validations and should only be
 * used as a starting point for your own implementation.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
$oSecrets = new GSecrets(GSecrets::TOKEN_FILE);
$oClient = new GClient();
$oClient->setOAuthClient($oSecrets->getOAuthClient());
$oClient->addScope(GContacts::CONTACTS);
$oClient->addScope(GContacts::CONTACTS_OTHER_READONLY);

$strAuthURL = $oClient->buildAuthURL();

header('Location: ' . filter_var($strAuthURL, FILTER_SANITIZE_URL));