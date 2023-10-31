<?php
declare(strict_types=1);

require_once 'autoloader.php';
require_once 'displayApiError.php';

use SKien\Google\GClient;
use SKien\Google\GSecrets;
use SKien\Google\VCardImport;

/**
 * This example is only intended to demonstrate the use of the package. The UI
 * is only coded 'quick and dirty', contains no validations and should only be
 * used as a starting point for your own implementation.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
if (isset($_FILES['vcfFile']) && $_FILES['vcfFile']['tmp_name'] != '') {
    // to test different own files use ImportSelect.html...)
    $strFilename = $_FILES['vcfFile']['tmp_name'];

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

    // to detect german umlauts in iso or windows files...
    mb_detect_order(['ASCII', 'UTF-8', 'ISO-8859-1', 'Windows-1252', 'Windows-1251']);

    $oImport = new VCardImport($oClient, VCardImport::OPT_CREATE_IMPORT_GROUP);
    if ($oImport->importVCard($strFilename) !== false) {
        if ($oImport->getImportCount() > 1) {
            header('Location: ./ContactList.php?group=' . filter_var($oImport->getImportGroup(), FILTER_SANITIZE_URL));
        } else {
            header('Location: ./ContactDetails.php?res=' . filter_var($oImport->getLastPersonResource(), FILTER_SANITIZE_URL));
        }
    } else {
        displayApiError(
            'VCard Import',
            '',
            $oClient->getLastResponseCode(),
            $oClient->getLastError(),
            $oClient->getLastStatus()
        );
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="ISO-8859-1">
<title>GCalendar vCard Importtest</title>
</head>
<body>
	<h2>import vCard - File</h2>
	<form enctype="multipart/form-data" method="post">
	<input type="file" id="vcfFile" name="vcfFile">
	<input type="submit" value="import vCard">
	</form>
</body>
</html>