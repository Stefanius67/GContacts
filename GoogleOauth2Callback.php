<?php
declare(strict_types=1);

use SKien\Google\GClient;
use SKien\Google\GSecrets;

require_once 'autoloader.php';

/**
 * This script is called by the google authentication.
 *
 * The code passed to this script is used to fetch the
 * access/refresh token that is needed to call the API
 *
 * This is the redirect URI for the used client
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
$oSecrets = new GSecrets(GSecrets::TOKEN_FILE);
$oClient = new GClient();
$oClient->setOAuthClient($oSecrets->getOAuthClient());
if ($oClient->fetchTokens($_GET['code'])) {
    $oSecrets->saveRefreshToken($oClient->getRefreshToken());
    $oSecrets->saveAccessToken($oClient->getAccessToken());
    header('Location: ./ContactList.php');
}
