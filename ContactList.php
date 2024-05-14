<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use SKien\Google\GClient;
use SKien\Google\GContact;
use SKien\Google\GContactGroups;
use SKien\Google\GContacts;
use SKien\Google\GSecrets;

require_once 'autoloader.php';
require_once 'displayApiError.php';

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

$strSearch = $_REQUEST['search'] ?? '';
$strGroup = $_REQUEST['group'] ?? '';

// load available user defined contact groups
$oGroups = new GContactGroups($oClient);
$aGroups = $oGroups->list(GContactGroups::GT_USER_CONTACT_GROUPS);
if ($aGroups === false) {
    displayApiError(
        'list contact groups',
        '',
        $oClient->getLastResponseCode(),
        $oClient->getLastError(),
        $oClient->getLastStatus()
        );
    exit;
}
$strGroupSelect  = '<select id="group" onchange="onSelectGroup(this);">' . PHP_EOL;
$strGroupSelect .= '                <option value="">&lt;all contacts&gt;</option>' . PHP_EOL;
foreach ($aGroups as $strGroupResourceName => $strGroupName) {
    $strSelected = ($strGroup == $strGroupResourceName ? ' selected' : '');
    $strGroupSelect .= '                <option value="' . $strGroupResourceName . '"' . $strSelected . '>' . $strGroupName . '</option>' . PHP_EOL;
}
$strGroupSelect .= '            </select>' . PHP_EOL;

$oContacts = new GContacts($oClient);
$oContacts->addPersonFields(GContacts::DEF_LIST_PERSON_FIELDS);

$aContactList = [];
$strTitle = '';
if (!empty($strSearch)) {
    $oContacts->setPageSize(50);
    $aContactList = $oContacts->search($strSearch);
    $strTitle = ' - Search for <b><i>[' . $strSearch . ']</i></b>';
    if ($aContactList === false) {
        displayApiError(
            'search contacts',
            'search: ' . $strSearch,
            $oClient->getLastResponseCode(),
            $oClient->getLastError(),
            $oClient->getLastStatus()
            );
        exit;
    }
} else {
    $aContactList = $oContacts->list(GContacts::SO_LAST_NAME_ASCENDING, $strGroup);
    if ($aContactList === false) {
        displayApiError(
            'list contacts',
            'group: ' . $strGroup,
            $oClient->getLastResponseCode(),
            $oClient->getLastError(),
            $oClient->getLastStatus()
            );
        exit;
    }
}
$strTitle = count($aContactList) . ' Contacts' . $strTitle;
?>
<html>
<head>
<style>
body, table {
    font-family: Sans-Serif;
    font-size: 12px;
}
a, a:visited {
    color: blue;
}
label {
    display: inline-block;
    width: 100px;
}
table {
    border-spacing: 0;
    border-collapse: collapse;
}
th {
    color: white;
    background-color: #777;
    border: 1px solid #333;
    padding: 2px 4px;
}
td {
    border: 1px solid #ccc;
    padding: 2px 4px;
}
tr:nth-child(2n+1) {
    background-color: #eee;
}
td:nth-child(9) {
    text-align: center;
}
a.trash {
    color: #999;
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
}
a.trash:hover {
    color: #009;
}
a.starred,
a.unstarred {
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
}
a.starred,
a.unstarred:hover {
    color: goldenrod;
}
a.starred:hover,
a.unstarred {
    color: #ccc;
}
</style>
<script>
function onSelectGroup(oSelect)
{
	window.location = './ContactList.php?group=' + encodeURI(oSelect.value);
}
function deleteContact(strResourceName)
{
	if (confirm("Delete the contact?")) {
		window.location = './DoAction.php?action=deleteContact&res=' + encodeURI(strResourceName);
	}
}
function createGroup()
{
	var strGroupName = prompt('Enter new group name', '');
	if (strGroupName !== null) {
		window.location = './DoAction.php?action=saveGroup&name=' + encodeURI(strGroupName);
	}
}
function deleteGroup()
{
	var strGroupName = prompt('Enter name of group to delete', '');
	if (strGroupName !== null) {
		window.location = './DoAction.php?action=deleteGroup&name=' + encodeURI(strGroupName);
	}
}
</script>
</head>
<body>
    <form action="./ContactList.php" method="post">
		<div>
            <label for="search">Query:</label>
            <input id="search" type="text" name="search" />
            <input type="submit" value="Search" />
            <br/><br/>
            <label for="group">or select group:</label>
            <?=$strGroupSelect?>
        </div>
    </form>
	<h2><?=$strTitle?></h2>
	<a href="./ContactDetails.php">Add new contact</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="javascript: createGroup()">Create new contactgroup</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="javascript: deleteGroup()">Delete existing contactgroup</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<a href="./ImportVCard.php">VCard Import</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	<br/><br/>
    <table style="width: 100%">
    	<tbody>
    		<tr>
    			<th style="width: 4%">#</th>
    			<th style="width: 3%">&nbsp;</th>
    			<th style="width: 22%">Name</th>
    			<th style="width: 18%" colspan="2">Phone 1</th>
    			<th style="width: 18%" colspan="2">Phone 2</th>
    			<th style="width: 25%">e-Mail</th>
    			<th style="width: 7%">&nbsp;</th>
    			<th style="width: 3%">&nbsp;</th>
    		</tr>
<?php
$i = 0;
foreach ($aContactList as $aContact) {
    $i++;
    $oContact = GContact::fromArray($aContact);
    $strResourceName = rawurlencode($aContact['resourceName']);
    $strStarrURL = './DoAction.php?action=starreContact&res=' . $strResourceName . '&setstarred=';
    $strDeleteFunc = "javascript: deleteContact('" . $strResourceName . "')";
    $strName = '[not set]';
    if (isset($aContact['names'][0])) {
        $strName = $aContact['names'][0]['displayNameLastFirst'];
    } else if (isset($aContact['organizations'][0])) {
        $strName = $aContact['organizations'][0]['name'];
    }
    echo '            <tr id="' . $aContact['resourceName'] . '">' . PHP_EOL;
    echo '                <td>' . $i . '</td>' . PHP_EOL;
    if ($oContact->isStarred()) {
        $strStarrURL;
        echo '                <td><a class="starred" href="' . $strStarrURL . 'false" title="unmark">&#x2605;</a></td>' . PHP_EOL;
    } else {
        echo '                <td><a class="unstarred" href="' . $strStarrURL . 'true" title="mark starred">&#x2606;</a></td>' . PHP_EOL;
    }
    echo '                <td><a href="./ContactDetails.php?res=' . $strResourceName . '">' . $strName . '</a></td>' . PHP_EOL;
    echo '                <td>' . (isset($aContact['phoneNumbers'][0]) ? $aContact['phoneNumbers'][0]['type'] : '') . '</td>' . PHP_EOL;
    echo '                <td>' . (isset($aContact['phoneNumbers'][0]) ? $aContact['phoneNumbers'][0]['value'] : '') . '</td>' . PHP_EOL;
    echo '                <td>' . (isset($aContact['phoneNumbers'][1]) ? $aContact['phoneNumbers'][1]['type'] : '') . '</td>' . PHP_EOL;
    echo '                <td>' . (isset($aContact['phoneNumbers'][1]) ? $aContact['phoneNumbers'][1]['value'] : '') . '</td>' . PHP_EOL;
    echo '                <td>' . (isset($aContact['emailAddresses'][0]) ? $aContact['emailAddresses'][0]['value'] : '') . '</td>' . PHP_EOL;
    echo '                <td><a href="./ExportVCard.php?res=' . $strResourceName . '" target="_blank">VCard</a></td>' . PHP_EOL;
    echo '                <td><a class="trash" href="' . $strDeleteFunc . '" title="delete contact">&#128465;</a></td>' . PHP_EOL;
    echo '            </tr>' . PHP_EOL;
}
?>
    	</tbody>
    </table>
</body>
</html>
