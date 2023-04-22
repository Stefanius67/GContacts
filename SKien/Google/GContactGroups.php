<?php
declare(strict_types=1);

namespace SKien\Google;

/**
 * Class to manage the contact groups of a google account.
 *
 * This class encapsulates the following Google People API resources:
 *  - contactGroups
 *  - contactGroups.members
 *
 * If one of the methods that calls the google API fails (if it returns ´false´),
 * the last responsecode and furter informations can be retrieved through
 * following methods of the ´GClient´ instance this object was created with:
 *  - ´$oClient->getLastResponseCode()´
 *  - ´$oClient->getLastError()´
 *  - ´$oClient->getLastStatus()´
 *
 * @see \SKien\Google\GClient::getLastResponseCode()
 * @see \SKien\Google\GClient::getLastError()
 * @see \SKien\Google\GClient::getLastStatus()
 *
 * @link https://developers.google.com/people/api/rest/v1/contactGroups
 * @link https://developers.google.com/people/api/rest/v1/contactGroups.members
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GContactGroups
{
    /** Values for the groupFields     */
    /** The group's client data
     *  @link https://developers.google.com/people/api/rest/v1/contactGroups#groupclientdata */
    public const GF_CLIENT_DATA = 'clientData';
    /** The contact group type
     *  @link https://developers.google.com/people/api/rest/v1/contactGroups#GroupType   */
    public const GF_GROUP_TYPE = 'groupType';
    /** The total number of contacts in the group irrespective of max members in specified in the request     */
    public const GF_MEMBER_COUNT = 'memberCount';
    /** The metadata about a contact group
     * @link https://developers.google.com/people/api/rest/v1/contactGroups#contactgroupmetadata     */
    public const GF_METADATA = 'metadata';
    /** name, formattedName, resourceName    */
    public const GF_NAME = 'name';

    /**	All available contact groups (User- and system groups) */
    public const GT_ALL_CONTACT_GROUPS = '';
    /**	User defined contact group */
    public const GT_USER_CONTACT_GROUPS = 'USER_CONTACT_GROUP';
    /** System defined contact group */
    public const GT_SYSTEM_CONTACT_GROUPS = 'SYSTEM_CONTACT_GROUP';

    /** full data list    */
    public const DATA_LIST = 0;
    /** list as associative array resourceName -> groupName    */
    public const RES_LIST = 1;

    /** resourceNames of the systemGroups   */
    public const GRP_STARRED = 'contactGroups/starred';
    public const GRP_CHAT_BUDDIES = 'contactGroups/chatBuddies';
    public const GRP_ALL = 'contactGroups/all';
    public const GRP_MY_CONTACTS = 'contactGroups/myContacts';
    public const GRP_FRIENDS = 'contactGroups/friends';
    public const GRP_FAMILY = 'contactGroups/family';
    public const GRP_COWORKERS = 'contactGroups/coworkers';
    public const GRP_BLOCKED = 'contactGroups/blocked';

    /** max. pagesize for the list request */
    protected const GROUPS_MAX_PAGESIZE = 1000;

    /** @var array<string> groupFields to be retruned by the request */
    protected array $aGroupFields = [];
    /** @var int pagesize for a request */
    protected int $iPageSize = 50;
    /** @var GClient    clients to perform the requests to the api     */
    protected GClient $oClient;

    /**
     * Create an instance and pass the client to use
     * for the API requests.
     * @param GClient $oClient
     */
    public function __construct(GClient $oClient)
    {
        $this->oClient = $oClient;
    }

    /**
     * Add groupFields for next request.
     * Can be called multiple and/or by passing an array of groupFields. All
     * const self::GF_xxxx can be specified.
     * Normally the groupFields parameter is left blank, which means that
     * all groupFields except GF_CLIENT_DATA are taken into account.
     * @param string|array<string>|null $fields; if set to null, the internal list is cleared
     */
    public function addGroupFields($fields) : void
    {
        if ($fields === null) {
            $this->aGroupFields = [];
        } else if (is_array($fields)) {
            $this->aGroupFields = array_merge($this->aGroupFields, $fields);
        } else if (!in_array($fields, $this->aGroupFields)) {
            $this->aGroupFields[] = $fields;
        }
    }

    /**
     * Set the pagesize for reading lists.
     * May be limited to the max. pagesize for the request.
     * @param int $iPageSize
     */
    public function setPageSize(int $iPageSize) : void
    {
        $this->iPageSize = $iPageSize;
    }

    /**
     * Get the whole list of the contact groups.
     * The type of the groups to l ist (All, User or System) can be specified by the
     * ´$strGroupType´ parameter.  <br/><br/>
     * The content of the result array can be specified with the ´$iListType´ parameter.
     * ´self::RES_LIST´:    all groups as associative array ´ressourceName´ -> ´groupName´.
     * ´self::DATA_LIST´:   an array of all ContactGroup - objects.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups/list
     * @link https://developers.google.com/people/api/rest/v1/contactGroups#ContactGroup
     * @param string $strGroupType  use one of the ´self::GT_xxx´ constants
     * @param int $iListType    content of the result array (´self::RES_LIST´ or ´self::DATA_LIST´)
     * @return array<mixed>|false
     */
    public function list(string $strGroupType = self::GT_ALL_CONTACT_GROUPS, int $iListType = self::RES_LIST)
    {
        $aHeader = [$this->oClient->getAuthHeader()];
        $aParams = [];
        if (count($this->aGroupFields) > 0) {
            $aParams['groupFields'] = implode(',', $this->aGroupFields);
        }
        $aParams['pageSize'] = ($this->iPageSize > self::GROUPS_MAX_PAGESIZE ? self::GROUPS_MAX_PAGESIZE : $this->iPageSize);

        $bEndOfList = false;
        $aGroupList = [];
        while ($bEndOfList === false) {
            $strURI = 'https://people.googleapis.com/v1/contactGroups?' . http_build_query($aParams);
            $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader);
            if ($strResponse === false) {
                return false;
            }
            $oResponse = json_decode($strResponse, true);
            if (isset($oResponse['nextPageToken']) && !empty($oResponse['nextPageToken'])) {
                $aParams['pageToken'] = $oResponse['nextPageToken'];
            } else {
                $bEndOfList = true;
            }
            foreach ($oResponse['contactGroups'] as $aGroup) {
                if ($strGroupType == self::GT_ALL_CONTACT_GROUPS || $aGroup['groupType'] == $strGroupType) {
                    if ($iListType == self::DATA_LIST) {
                        $aGroupList[] = $aGroup;
                    } else {
                        $aGroupList[$aGroup['resourceName']] = $aGroup['formattedName'];
                    }
                }
            }
        }
        return $aGroupList;
    }

    /**
     * Get whole group information.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups/get
     * @link https://developers.google.com/people/api/rest/v1/contactGroups#ContactGroup
     * @param string $strResourceName
     * @param int $iMaxMembers
     * @return array<mixed>|false
     */
    public function getGroup(string $strResourceName, int $iMaxMembers = 0)
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        $aParams = [];
        if (count($this->aGroupFields) > 0) {
            $aParams['updatePersonFields'] = implode(',', $this->aGroupFields);
        }
        if ($iMaxMembers > 0) {
            $aParams['maxMembers'] = $iMaxMembers;
        }

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . '?' . http_build_query($aParams);
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader);

        $result = false;
        if ($strResponse !== false) {
            $result = json_decode($strResponse, true);
        }
        return $result;
    }

    /**
     * Get the resourceName for a named contact group.
     * If no group with requested name found, the method will return
     * an empty string.
     * @param string $strGroupName
     * @return string|false resourceName or false in case of an error
     */
    public function getGroupResourceName(string $strGroupName)
    {
        // get grouplist and search for requested name
        $aGroups = $this->list(self::GT_ALL_CONTACT_GROUPS, self::DATA_LIST);
        if ($aGroups !== false) {
            foreach ($aGroups as $aGroup) {
                if ($aGroup['formattedName'] == $strGroupName) {
                    return $aGroup['resourceName'];
                }
            }
            return ''; // ... no error, but group not found
        }
        return false;
    }

    /**
     * Create a new contact group.
     * The name must be unique to the users contact groups. Attempting to create
     * a group with a duplicate name results in a 409 response code.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups/create
     * @param string $strGroupName
     * @return array<mixed>|false
     */
    public function createGroup(string $strGroupName)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];
        $data = json_encode(['contactGroup' => ['name' => $strGroupName]]);

        $strURI = 'https://people.googleapis.com/v1/contactGroups';
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::POST, $aHeader, $data);

        $result = false;
        if ($strResponse !== false) {
            $result = json_decode($strResponse, true);
        }
        return $result;
    }

    /**
     * Update the groupName for the group specified by the resourceName.
     * The new name must be unique to the users contact groups. Attempting to rename
     * a group with a duplicate name results in a 409 response code.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups/update
     * @param string $strResourceName
     * @param string $strGroupName
     * @return array<mixed>|false
     */
    public function updateGroup(string $strResourceName, string $strGroupName)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];
        $data = json_encode(['contactGroup' => ['name' => $strGroupName]]);

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName;
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::PUT, $aHeader, $data);

        $result = false;
        if ($strResponse !== false) {
            $result = json_decode($strResponse, true);
        }
        return $result;
    }

    /**
     * Delete the contact group specified by the resourceName.
     * If the param ´$bDeleteContacts´ is set to true, ALL contacts that are asigned to
     * the group to delete will be <b>deleted</b> to!
     * @link https://developers.google.com/people/api/rest/v1/contactGroups/delete
     * @param string $strResourceName
     * @param bool $bDeleteContacts set to true, if all groupmembers should be deleted
     * @return bool true on success, false on error.
     */
    public function deleteGroup(string $strResourceName, bool $bDeleteContacts = false) : bool
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName;
        if ($bDeleteContacts) {
            $strURI .= '?' . http_build_query(['deleteContacts' => true]);
        }
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::DELETE, $aHeader);
        return ($strResponse !== false);
    }

    /**
     * Add one or more contacts to the specified group.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups.members/modify
     * @param string $strGroupResourceName
     * @param array<string> $aContactResourceNames
     * @return array<mixed>|false
     */
    public function addContactsToGroup(string $strGroupResourceName, array $aContactResourceNames)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];
        $data = json_encode(['resourceNamesToAdd' => $aContactResourceNames]);

        $strURI = 'https://people.googleapis.com/v1/' . $strGroupResourceName . '/members:modify';
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::POST, $aHeader, $data);

        $result = false;
        if ($strResponse !== false) {
            $result = json_decode($strResponse, true);
        }
        return $result;
    }

    /**
     * Remove one or more contacts from the specified group.
     * @link https://developers.google.com/people/api/rest/v1/contactGroups.members/modify
     * @param string $strGroupResourceName
     * @param array<string> $aContactResourceNames
     * @return array<mixed>|false
     */
    public function removeContactsFromGroup(string $strGroupResourceName, array $aContactResourceNames)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];
        $data = json_encode(['resourceNamesToRemove' => $aContactResourceNames]);

        $strURI = 'https://people.googleapis.com/v1/' . $strGroupResourceName . '/members:modify';
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::POST, $aHeader, $data);

        $result = false;
        if ($strResponse !== false) {
            $result = json_decode($strResponse, true);
        }
        return $result;
    }
}
