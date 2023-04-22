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

$oContacts = new GContacts($oClient);
$oContacts->addPersonFields(GContacts::DEF_DETAIL_PERSON_FIELDS);
$strResourceName = rawurldecode($_GET['res'] ?? '');

if (empty($strResourceName)) {
    $oContact = GContact::createEmpty();
} else {
    $oContact = $oContacts->getContact($strResourceName);
}
$uxtsLastModified = $oContact->getLastModified();

$oGroups = new GContactGroups($oClient);
$aGroups = $oGroups->list(GContactGroups::GT_USER_CONTACT_GROUPS);
$strGroups = '';
foreach ($oContact['memberships'] as $aMembership) {
    if (isset($aMembership['contactGroupMembership'])) {
        $strGroupRes = $aMembership['contactGroupMembership']['contactGroupResourceName'];
        if (isset($aGroups[$strGroupRes])) {
            $strGroups .= '                    <li>' . $aGroups[$strGroupRes] . '</li>' . PHP_EOL;
        }
    }
}

file_put_contents('./data/' . str_replace(' ', '_', $oContact->getDisplayName()) . '.json', json_encode($oContact, JSON_PRETTY_PRINT));

$strTitle = $oContact->getDisplayName();
if ($oContact->isStarred()) {
    $strTitle = '&#x2b50;&nbsp;' . $strTitle;
}

?>
<html>
<head>
<style>
body, form {
    font-family: Sans-Serif;
    font-size: 12px;
}
form fieldset {
    line-height: 200%;
}
form label {
    display: inline-block;
    width: 150px;
}
form input[type=text] {
    width: 400px;
}
form span img {
    float: right;
}
form label.ptype {
    width: 80px;
    text-align: right;
}
for, input[type=tel] {
    width: 236px;
}
form input.ptype {
    width: 80px;
}
form input.mail {
    width: 236px;
}
form input.city {
    width: 316px;
}
form textarea {
    width: 552px;
}
form ul {
    margin: -10px 0 -5px 0;
}
</style>
<script>
function onPhotoFileSelected()
{
	// var file = document.getElementById('photoFile').files[document.getElementById('photoFile').files.length - 1];
	// alert(file.name);
	document.getElementById('photoForm').submit();
}
function loadPhoto()
{
	document.getElementById('photoFile').click()
}
function deletePhoto(strResourceName)
{
	if (confirm("Delete the photo?")) {
		window.location = './DoAction.php?action=deleteContactPhoto&res=' + encodeURI(strResourceName);
	}
}
</script>
</head>
<body>
	<a href="./ContactList.php">back to the overview</a><br/><br/>
    <form id="photoForm" action="./DoAction.php?action=setContactPhoto" enctype="multipart/form-data" method="post">
		<input type="file" id="photoFile" name="photoFile" onchange="onPhotoFileSelected()" style="display: none">
		<input type="hidden" name="resourceName" value="<?=$strResourceName?>">
	</form>
    <form action="./DoAction.php?action=saveContact" method="post">
		<input type="hidden" name="resourceName" value="<?=$strResourceName?>">
		<input type="hidden" name="metadataType" value="<?=$oContact['metadata']['sources'][0]['type']?>">
		<input type="hidden" name="metadataId" value="<?=$oContact['metadata']['sources'][0]['id']?>">
		<input type="hidden" name="metadataEtag" value="<?=$oContact['metadata']['sources'][0]['etag']?>">
		<h2><?=$strTitle?></h2>
		<p>Letzte Änderung: <?php if ($uxtsLastModified > 0) echo date('d.m.Y - H:i:s', $uxtsLastModified); ?></p>
		<div>
<?php
if (strlen($strGroups) > 0) {
    echo '            <fieldset>' . PHP_EOL;
    echo '                <legend>Member of</legend>' . PHP_EOL;
    echo '                <ul>' . PHP_EOL;
    echo $strGroups;
    echo '                </ul>' . PHP_EOL;
    echo '            </fieldset>' . PHP_EOL;
}
?>
			<fieldset>
				<legend>Names</legend>
				<div style="float: right; padding: 10px;">
					Photo&nbsp;
					<a href="javascript: loadPhoto()" title="Select photo" style="font-size: 20px; text-decoration: none; padding: 0 8px;">&#128393;</a>&nbsp;
					<a href="javascript: deletePhoto('<?=$strResourceName?>')" title="Remove photo" style="font-size: 20px; text-decoration: none; padding: 0 8px;">&#128465;</a>
					<br/>
					<img onclick="loadPhoto()" src="<?php echo $oContact['photos'][0]['url'] ?? ''?>">
    			</div>
    			<label for="honorificPrefix">honorificPrefix:</label>
    			<input type="text" id="honorificPrefix" name="names_0_honorificPrefix" value="<?php echo $oContact['names'][0]['honorificPrefix'] ?? ''?>">
    			<br/>
    			<label for="familyName">familyName:</label>
    			<input type="text" id="familyName" name="names_0_familyName" value="<?php echo $oContact['names'][0]['familyName'] ?? ''?>">
    			<br/>
    			<label for="givenName">givenName:</label>
    			<input type="text" id="givenName" name="names_0_givenName" value="<?php echo $oContact['names'][0]['givenName'] ?? ''?>">
    			<br/>
    			<label for="middleName">middleName:</label>
    			<input type="text" id="middleName" name="names_0_middleName" value="<?php echo $oContact['names'][0]['middleName'] ?? ''?>">
    			<br/>
    			<label for="honorificSuffix">honorificSuffix:</label>
    			<input type="text" id="honorificSuffix" name="names_0_honorificSuffix" value="<?php echo $oContact['names'][0]['honorificSuffix'] ?? ''?>">
    			<br/>
<?php
$i = 0;
foreach ($oContact['nicknames'] as $aNickname) {
    $strName = 'nicknames_' . $i . '_value';
    $strField = 'nickName' . ++$i;
    echo '                <label for="' . $strField . '">' . $strField . ':</label>' . PHP_EOL;
    echo '                <input type="text" id="' . $strField . '" name="' . $strName . '" value="' . $aNickname['value'] . '">' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
}
?>
    			<label for="birthday">birthday:</label>
    			<input type="date" id="birthday" name="birthday" value="<?=$oContact->getDateOfBirth(GContact::DT_STRING)?>">
    			<label class="ptype" for="gender">gender:</label>
    			<select id="gender" name="genders_0_value">
    				<option value=""></option>
    				<option value="male"<?php echo ($oContact['genders'][0]['value'] ?? '') == 'male' ? ' selected' : '';?>>male</option>
    				<option value="female"<?php echo ($oContact['genders'][0]['value'] ?? '') == 'female' ? ' selected' : '';?>>female</option>
    				<option value="unspecified"<?php echo ($oContact['genders'][0]['value'] ?? '') == 'unspecified' ? ' selected' : '';?>>unspecified</option>
    			</select>
    			<br/>
			</fieldset>
			<fieldset>
				<legend>Phone numbers</legend>
<?php
$i = 0;
foreach ($oContact['phoneNumbers'] as $aPhone) {
    $strFieldName = 'phoneNumbers_' . $i . '_value';
    $strTypeName = 'phoneNumbers_' . $i . '_type';
    $strField = 'phoneNumber' . ++$i;
    $strType = 'phoneType' . $i;
    $strPrimary = $oContact->isPrimaryItem($aPhone) ? ' checked' : '';
    echo '                <label for="' . $strField . '">' . $strField . ':</label>' . PHP_EOL;
    echo '                <input type="tel" id="' . $strField . '" name="' . $strFieldName . '" value="' . $aPhone['value'] . '">' . PHP_EOL;
    echo '                <label class="ptype" for="' . $strType . '">type:</label>' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="' . $strType . '" name="' . $strTypeName . '" value="' . ($aPhone['type'] ?? 'other') . '">' . PHP_EOL;
    echo '                <input type="radio" name="phoneNumbers" value="' . ($i-1) . '"' . $strPrimary . '>&nbsp;primary phone' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
}
?>
			</fieldset>
			<fieldset>
				<legend>Mailaddresses</legend>
<?php
$i = 0;
foreach ($oContact['emailAddresses'] as $aMail) {
    $strFieldName = 'emailAddresses_' . $i . '_value';
    $strTypeName = 'emailAddresses_' . $i . '_type';
    $strField = 'emailAddress' . ++$i;
    $strType = 'emailType' . $i;
    $strPrimary = $oContact->isPrimaryItem($aMail) ? ' checked' : '';
    echo '                <label for="' . $strField . '">' . $strField . ':</label>' . PHP_EOL;
    echo '                <input class="mail" type="text" id="' . $strField . '" name="' . $strFieldName . '" value="' . $aMail['value'] . '">' . PHP_EOL;
    echo '                <label class="ptype" for="' . $strType . '">type:</label>' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="' . $strType . '" name="' . $strTypeName . '" value="' . ($aMail['type'] ?? 'other') . '">' . PHP_EOL;
    echo '                <input type="radio" name="emailAddresses" value="' . ($i-1) . '"' . $strPrimary . '>&nbsp;primary email' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
}
?>
			</fieldset>
			<fieldset>
				<legend>Organization</legend>
				<label for="orgName">orgName:</label>
                <input type="text" id="orgName" name="organizations_0_name" value="<?php echo ($oContact['organizations'][0]['name'] ?? '')?>">
                <br/>
                <label for="orgTitle">orgTitle:</label>
                <input type="text" id="orgTitle" name="organizations_0_title" value="<?php echo ($oContact['organizations'][0]['title'] ?? '')?>">
                <br/>
                <label for="orgDepartment">orgDepartment:</label>
                <input type="text" id="orgDepartment" name="organizations_0_department" value="<?php echo ($oContact['organizations'][0]['department'] ?? '')?>">
                <br/>
			</fieldset>
			<fieldset>
				<legend>Addresses</legend>
<?php
$i = 0;
foreach ($oContact['addresses'] as $aAdr) {
    ++$i;
    if ($i > 1) {
        echo '                <hr>' . PHP_EOL;
    }
    $strPrimary = $oContact->isPrimaryItem($aAdr) ? ' checked' : '';
    echo '                <label for="adrType' . $i . '">type:</label>' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="adrType' . $i . '" name="addresses_' . ($i-1) . '_type" value="' . $aAdr['type'] . '">' . PHP_EOL;
    echo '                <input type="radio" name="addresses" value="' . ($i-1) . '"' . $strPrimary . '>&nbsp;primary address' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
    echo '                <label for="adrStreet' . $i . '">adrStreet:</label>' . PHP_EOL;
    echo '                <input class="city" type="text" id="adrStreet' . $i . '" name="addresses_' . ($i-1) . '_streetAddress" value="' . $aAdr['streetAddress'] . '">' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="extendedAddress' . $i . '" name="addresses_' . ($i-1) . '_extendedAddress" value="' . ($aAdr['extendedAddress'] ?? '') . '">' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
    echo '                <label for="adrPostcode' . $i . '">adrPostcode/adrCity:</label>' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="adrPostcode' . $i . '" name="addresses_' . ($i-1) . '_postalCode" value="' . $aAdr['postalCode'] . '">' . PHP_EOL;
    echo '                <input class="city" type="text" id="adrCity' . $i . '" name="addresses_' . ($i-1) . '_city" value="' . $aAdr['city'] . '">' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
    echo '                <label for="adrCountry' . $i . '">adrCountry/Code:</label>' . PHP_EOL;
    echo '                <input class="city" type="text" id="adrCountry' . $i . '" name="addresses_' . ($i-1) . '_country" value="' . ($aAdr['country'] ?? '') . '">' . PHP_EOL;
    echo '                <input class="ptype" type="text" id="adrCountryCode' . $i . '" name="addresses_' . ($i-1) . '_countryCode" value="' . ($aAdr['countryCode'] ?? '') . '">' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
    echo '                <label for="adrPOBox' . $i . '">adrPOBox:</label>' . PHP_EOL;
    echo '                <input type="text" id="adrPOBox' . $i . '" name="addresses_' . ($i-1) . '_poBox" value="' . ($aAdr['poBox'] ?? '') . '">' . PHP_EOL;
    echo '                <br/>' . PHP_EOL;
}
?>
			</fieldset>
			<fieldset>
				<legend>Notes (biographies)</legend>
				<textarea name="biographies_0_value" rows="4"><?php echo ($oContact['biographies'][0]['value'] ?? '')?></textarea>
			</fieldset>
        </div>
		<div>
			<br/>
            <input type="submit" value="Save" />
        </div>
    </form>
</body>
</html>
