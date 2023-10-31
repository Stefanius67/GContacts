<?php
declare(strict_types=1);

require_once 'autoloader.php';
require_once 'displayApiError.php';

use SKien\Google\GClient;
use SKien\Google\GContact;
use SKien\Google\GContactGroups;
use SKien\Google\GContactToVCard;
use SKien\Google\GContacts;
use SKien\Google\GSecrets;
use SKien\VCard\VCard;

/**
 * This example is only intended to demonstrate the use of the package. The UI
 * is only coded 'quick and dirty', contains no validations and should only be
 * used as a starting point for your own implementation.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */

$oSecrets = new GSecrets(-1, GSecrets::TOKEN_FILE);
$oClient = new GClient();
$oClient->setAccessToken($oSecrets->getAccessToken());
if ($oClient->isAccessTokenExpired()) {
    // try to refresh the accesstoken
    $strRefreshToken = $oSecrets->getRefreshToken();
    if (empty($strRefreshToken)) {
        // no refresh token available - redirect to google login
        header('Location: ./GoogleLogin.php');
        exit;
    }
    $oClient->setOAuthClient($oSecrets->getClientSecrets());
    $oSecrets->saveAccessToken($oClient->refreshAccessToken($strRefreshToken));
}

$oContacts = new GContacts($oClient);
$oContacts->addPersonFields(GContacts::DEF_DETAIL_PERSON_FIELDS);
$strResourceName = rawurldecode($_GET['res'] ?? '');

$oGroups = new GContactGroups($oClient);
$aGroups = $oGroups->list(GContactGroups::GT_USER_CONTACT_GROUPS, GContactGroups::RES_LIST);

$oVCard = new VCard();

if (empty($strResourceName)) {
    // no resource specified - export all contacts
    $aContactList = $oContacts->list(GContacts::SO_LAST_NAME_ASCENDING);
    if ($aContactList === false) {
        displayApiError(
            'list contacts',
            'group: ',
            $oClient->getLastResponseCode(),
            $oClient->getLastError(),
            $oClient->getLastStatus()
            );
        exit;
    }
    foreach ($aContactList as $aContact) {
        $oContact = GContact::fromArray($aContact);
        $oVContact = GContactToVCard::fromGContact($oContact);

        if ($aGroups !== false) {
            foreach ($oContact['memberships'] as $aMembership) {
                if (isset($aMembership['contactGroupMembership'])) {
                    $strResourceName = $aMembership['contactGroupMembership']['contactGroupResourceName'] ?? '';
                    if (isset($aGroups[$strResourceName])) {
                        $oVContact->addCategory($aGroups[$strResourceName]);
                    }
                }
            }
        }

        $oVCard->addContact($oVContact);
    }
} else {
    $oContact = $oContacts->getContact($strResourceName);
    if ($oContact === false) {
        displayApiError(
            'reading contact',
            $strResourceName,
            $oClient->getLastResponseCode(),
            $oClient->getLastError(),
            $oClient->getLastStatus()
            );
        exit;
    }
    $oVContact = GContactToVCard::fromGContact($oContact);

    if ($aGroups !== false) {
        foreach ($oContact['memberships'] as $aMembership) {
            if (isset($aMembership['contactGroupMembership'])) {
                $strResourceName = $aMembership['contactGroupMembership']['contactGroupResourceName'] ?? '';
                if (isset($aGroups[$strResourceName])) {
                    $oVContact->addCategory($aGroups[$strResourceName]);
                }
            }
        }
    }

    $oVCard->addContact($oVContact);
}

// and write to file
$oVCard->write('gcontacts.vcf', false);