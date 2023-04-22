<?php
declare(strict_types=1);

use SKien\Google\GClient;
use SKien\Google\GContact;
use SKien\Google\GContactGroups;
use SKien\Google\GContacts;
use SKien\Google\GSecrets;

require_once 'autoloader.php';

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
    $oClient->setOAuthClient($oSecrets->getOAuthClient());
    $oSecrets->saveAccessToken($oClient->refreshAccessToken($strRefreshToken));
}

$strAction = $_GET['action'] ?? 'saveContact';

$result = false;
$strLocation = '';
switch ($strAction) {
    case 'saveContact':
        $oContacts = new GContacts($oClient);
        $oContacts->addPersonFields(GContacts::DEF_DETAIL_PERSON_FIELDS);

        // start with an empty object
        $oContact = GContact::createEmpty();

        $strResourceName = $_POST['resourceName'];
        $bCreateContact = false;
        if (!empty($strResourceName)) {
            $strAction = 'saving contact';
            $oContact->setMetaData($_POST['metadataType'], $_POST['metadataId'], $_POST['metadataEtag']);
        } else {
            $strAction = 'creating contact';
            $bCreateContact = true;
        }
        // get all 'pathed' values
        foreach ($_POST as $strName => $strValue) {
            $aPath = explode('_', $strName);
            if (count($aPath) == 3) {
                $iIndex = intval($aPath[1]);
                if (!isset($oContact[$aPath[0]][$iIndex])) {
                    $oContact[$aPath[0]][$iIndex] = [];
                }
                $oContact[$aPath[0]][$iIndex][$aPath[2]] = $strValue;
            }
        }
        // set the primary items
        $aPrimaryRadios = [
            GContact::PF_ADDRESSES,
            GContact::PF_EMAIL_ADDRESSES,
            GContact::PF_PHONE_NUMBERS,
            GContact::PF_URLS,
        ];
        foreach ($aPrimaryRadios as $strPrimary) {
            if (isset($_POST[$strPrimary])) {
                $oContact->setPrimaryItem($strPrimary, intval($_POST[$strPrimary]));
            }
        }
        // handle birthday separate...
        if (strlen($_POST['birthday']) > 0) {
            $oContact->setDateOfBirth($_POST['birthday']);
        }
        if ($bCreateContact) {
            $oContact = $oContacts->createContact($oContact);
            if ($oContact !== false) {
                $result = true;
                $strLocation = './ContactDetails.php?res=' . $oContact->getResourceName();
            }
        } else {
            if ($oContacts->updateContact($strResourceName, $oContact) !== false) {
                $result = true;
                $strLocation = './ContactDetails.php?res=' . $strResourceName;
            }
        }
        break;
    case 'deleteContact':
        $strAction = 'deleting contact';
        $oContacts = new GContacts($oClient);

        $strResourceName = rawurldecode($_GET['res'] ?? '');
        if (!empty($strResourceName)) {
            $result = $oContacts->deleteContact($strResourceName);
            $strLocation = './ContactList.php';
        }
        break;
    case 'starreContact':
        $strAction = 'starre/unstarre contact';
        $oContacts = new GContacts($oClient);

        $strResourceName = rawurldecode($_GET['res'] ?? '');
        if (!empty($strResourceName)) {
            $result = $oContacts->setContactStarred($strResourceName, $_GET['setstarred'] == 'true');
            $strLocation = './ContactList.php';
        }
        break;
    case 'setContactPhoto':
        $strAction = 'setting contact photo';
        $oContacts = new GContacts($oClient);
        $strResourceName = $_POST['resourceName'];
        if (isset($_FILES['photoFile']) && $_FILES['photoFile']['tmp_name'] != '') {
            $strFilename = $_FILES['photoFile']['tmp_name'];
            $result = $oContacts->setContactPhotoFile($strResourceName, $strFilename);
            $strLocation = './ContactDetails.php?res=' . $strResourceName;
        }
        break;
    case 'deleteContactPhoto':
        $strAction = 'deleting contact photo';
        $oContacts = new GContacts($oClient);
        $strResourceName = $_GET['res'];
        $result = $oContacts->deleteContactPhoto($strResourceName);
        $strLocation = './ContactDetails.php?res=' . $strResourceName;
        break;
    case 'saveGroup':
        $strAction = 'saving contact group';
        $oGroups = new GContactGroups($oClient);

        $strResourceName = rawurldecode($_GET['res'] ?? '');
        $strGroupName = rawurldecode($_GET['name'] ?? '');

        if (empty($strResourceName)) {
            $oGroup = $oGroups->createGroup($strGroupName);
        } else {
            $oGroup = $oGroups->updateGroup($strResourceName, $strGroupName);
        }
        $result = ($oGroup !== false);
        $strLocation = './ContactList.php';
        break;
    case 'deleteGroup':
        $strAction = 'deleting contact group';
        $oGroups = new GContactGroups($oClient);

        $strResourceName = rawurldecode($_GET['res'] ?? '');
        $strGroupName = rawurldecode($_GET['name'] ?? '');
        if (empty($strResourceName) && !empty($strGroupName)) {
            $strResourceName = $oGroups->getGroupResourceName($strGroupName);
        }
        if ($strResourceName !== false && !empty($strResourceName)) {
            $result = $oGroups->deleteGroup($strResourceName);
            $strLocation = './ContactList.php';
        }
        break;
}
if ($result !== false && !empty($strLocation)) {
    header('Location: ' . $strLocation);
    exit;
}
?>
<html>
<head>
<style>
body, table {
    font-family: Sans-Serif;
    font-size: 12px;
}
table {
    border-spacing: 0;
    border-collapse: collapse;
}
td {
    border: 1px solid #ccc;
    padding: 2px 4px;
}
tr td:nth-child(1) {
    font-weight: bold;
}
</style>
</head>
<body>
<h2>Error <?=$strAction?></h2>
<table>
	<tbody>
		<tr>
			<td>ressourceName</td>
			<td><?=$strResourceName?></td>
		</tr>
		<tr>
			<td>Responsecode</td>
			<td><?=$oClient->getLastResponseCode()?></td>
		</tr>
		<tr>
			<td>Message</td>
			<td><?=$oClient->getLastError()?></td>
		</tr>
		<tr>
			<td>Status</td>
			<td><?=$oClient->getLastStatus()?></td>
		</tr>
	</tbody>
</table>
</body>
</html>
