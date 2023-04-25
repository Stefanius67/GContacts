<?php
declare(strict_types=1);

namespace SKien\Google;

/**
 * Class to manage the contacts of a google account.
 *
 * This class encapsulates the following Google People API resources:
 *  - people
 *  - people.connections
 *
 * If one of the methods that calls the google API fails (if it returns `false`),
 * the last responsecode and furter informations can be retrieved through
 * following methods of the `GClient` instance this object was created with:
 *  - `$oClient->getLastResponseCode()`
 *  - `$oClient->getLastError()`
 *  - `$oClient->getLastStatus()`
 *
 * @see \SKien\Google\GClient::getLastResponseCode()
 * @see \SKien\Google\GClient::getLastError()
 * @see \SKien\Google\GClient::getLastStatus()
 *
 * @link https://developers.google.com/people/api/rest/v1/people
 * @link https://developers.google.com/people/api/rest/v1/people.connections
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GContacts
{
    /** full access to the users contacts */
    public const CONTACTS = "https://www.googleapis.com/auth/contacts";
    /** readonly access to the users contacts */
    public const CONTACTS_READONLY = "https://www.googleapis.com/auth/contacts.readonly";
    /** readonly access to the users other contacts */
    public const CONTACTS_OTHER_READONLY = "https://www.googleapis.com/auth/contacts.other.readonly";

    /**	Sort people by when they were changed; older entries first. */
    public const SO_LAST_MODIFIED_ASCENDING = 'LAST_MODIFIED_ASCENDING';
    /**	Sort people by when they were changed; newer entries first. */
    public const SO_LAST_MODIFIED_DESCENDING = 'LAST_MODIFIED_DESCENDING';
    /**	Sort people by first name. */
    public const SO_FIRST_NAME_ASCENDING = 'FIRST_NAME_ASCENDING';
    /**	Sort people by last name. */
    public const SO_LAST_NAME_ASCENDING = 'LAST_NAME_ASCENDING';

    /** the default personFields for detail view    */
    public const DEF_DETAIL_PERSON_FIELDS = [
        GContact::PF_NAMES,
        GContact::PF_ORGANIZATIONS,
        GContact::PF_NICKNAMES,
        GContact::PF_BIRTHDAYS,
        GContact::PF_PHOTOS,
        GContact::PF_ADDRESSES,
        GContact::PF_EMAIL_ADDRESSES,
        GContact::PF_PHONE_NUMBERS,
        GContact::PF_GENDERS,
        GContact::PF_MEMBERSHIPS,
        GContact::PF_METADATA,
        GContact::PF_BIOGRAPHIES,
        GContact::PF_URLS,
    ];

    /** the default personFields for detail view    */
    public const DEF_LIST_PERSON_FIELDS = [
        GContact::PF_NAMES,
        GContact::PF_ORGANIZATIONS,
        GContact::PF_NICKNAMES,
        GContact::PF_BIRTHDAYS,
        GContact::PF_ADDRESSES,
        GContact::PF_EMAIL_ADDRESSES,
        GContact::PF_PHONE_NUMBERS,
        GContact::PF_MEMBERSHIPS,
        GContact::PF_METADATA,
    ];

    /** max. pagesize for the list request */
    protected const CONTACTS_MAX_PAGESIZE = 1000;
    /** max. pagesize for a search */
    protected const SEARCH_MAX_PAGESIZE = 30;

    /** @var array<string>  personFields/readMask to be returned by the request */
    protected array $aPersonFields = [];
    /** pagesize for a request */
    protected int $iPageSize = 200;

    /** @var GClient    clients to perform the requests to the api     */
    protected GClient $oClient;

    /**
     * Create instance an pass the clinet for the requests.
     * @param GClient $oClient
     */
    public function __construct(GClient $oClient)
    {
        $this->oClient = $oClient;
    }

    /**
     * Add personFields/readMask for next request.
     * Can be called multiple and/or by passing an array of personFields. All
     * const `GContact::PF_xxxx` can be specified.
     * @param string|array<string>|null $fields; if set to null, the internal array is cleared
     */
    public function addPersonFields($fields) : void
    {
        if ($fields === null) {
            $this->aPersonFields = [];
        } else if (is_array($fields)) {
            $this->aPersonFields = array_merge($this->aPersonFields, $fields);
        } else if (!in_array($fields, $this->aPersonFields)) {
            $this->aPersonFields[] = $fields;
        }
    }

    /**
     * Set the pagesize for reading lists.
     * May be limited to the max. pgaesize for the request.
     * - contact list: max. pagesize is 1000
     * - search: max. pagesize is 30
     * @param int $iPageSize
     */
    public function setPageSize(int $iPageSize) : void
    {
        $this->iPageSize = $iPageSize;
    }

    /**
     * Get the whole contact list.
     * @link https://developers.google.com/people/api/rest/v1/people.connections/list
     * @param string $strSortOrder  one of the self::SO_xxx constants
     * @param string $strGroupResourceName
     * @return array<mixed>|false
     */
    public function list(string $strSortOrder = self::SO_LAST_NAME_ASCENDING, string $strGroupResourceName = '')
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        if (count($this->aPersonFields) == 0) {
            $this->aPersonFields = self::DEF_LIST_PERSON_FIELDS;
        }

        $aParams = [
            'personFields' => implode(',', $this->aPersonFields),
            'sortOrder' => $strSortOrder,
            'pageSize' => ($this->iPageSize > self::CONTACTS_MAX_PAGESIZE ? self::CONTACTS_MAX_PAGESIZE : $this->iPageSize),
        ];

        $bEndOfList = false;
        $aContactList = [];
        while ($bEndOfList === false) {
            $strURI = 'https://people.googleapis.com/v1/people/me/connections?' . http_build_query($aParams);
            $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader);
            if ($strResponse === false) {
                return false;
            }
            $oResponse = json_decode($strResponse, true);
            if (is_array($oResponse)) {
                if (isset($oResponse['nextPageToken']) && !empty($oResponse['nextPageToken'])) {
                    $aParams['pageToken'] = $oResponse['nextPageToken'];
                } else {
                    $bEndOfList = true;
                }
                foreach ($oResponse['connections'] as $aContact) {
                    if (empty($strGroupResourceName) || GContact::fromArray($aContact)->belongsToGroup($strGroupResourceName)) {
                        $aContactList[] = $aContact;
                    }
                }
            } else {
                break;
            }
        }
        return $aContactList;
    }

    /**
     * Search within the contacts.
     * The query matches on a contact's `names`, `nickNames`, `emailAddresses`,
     * `phoneNumbers`, and `organizations` fields. The search for phone numbers only
     * works, if leading '+' and contained '(', ')' or spaces are omitted in the query!
     * The query is used to match <b>prefix</B> phrases of the fields on a person.
     * For example, a person with name "foo name" matches queries such as "f", "fo",
     * "foo", "foo n", "nam", etc., but not "oo n".
     * > <b>Note:</b>
     * > The count of contacts, the search request returns is limitetd to the
     * > pageSize (which is limited itself to max. 30 at all). If there are
     * > more contacts in the list that matches the query, unfortunately NO
     * > further information about that additional contacts - and how many - are
     * > available!
     * @link https://developers.google.com/people/api/rest/v1/people/searchContacts
     * @param string $strQuery  the query to search for
     * @return array<mixed>|false
     */
    public function search(string $strQuery)
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        if (count($this->aPersonFields) == 0) {
            $this->aPersonFields = self::DEF_LIST_PERSON_FIELDS;
        }

        $aParams = [
            'query' => '',
            'readMask' => implode(',', $this->aPersonFields),
            'pageSize' => ($this->iPageSize > self::SEARCH_MAX_PAGESIZE ? self::SEARCH_MAX_PAGESIZE : $this->iPageSize),
        ];
        $strURI = 'https://people.googleapis.com/v1/people:searchContacts?' . http_build_query($aParams);

        // 'warmup' request
        // Note from google documentation:
        // Before searching, clients should send a warmup request with an empty query to update the cache.
        // https://developers.google.com/people/v1/contacts#search_the_users_contacts
        $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader);
        $aParams['query'] = $strQuery;
        $strURI = 'https://people.googleapis.com/v1/people:searchContacts?' . http_build_query($aParams);

        $aContactList = false;
        if (($strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader)) !== false) {
            $aContactList = [];
            $oResponse = json_decode($strResponse, true);
            if (is_array($oResponse) && isset($oResponse['results']) && count($oResponse['results']) > 0) {
                foreach ($oResponse['results'] as $aContact) {
                    $aContactList[] = $aContact['person'];
                }
            }
        }
        return $aContactList;
    }

    /**
     * Get the contact specified by its resourceName.
     * @link https://developers.google.com/people/api/rest/v1/people/get
     * @param string $strResourceName
     * @return GContact|false
     */
    public function getContact(string $strResourceName)
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        if (count($this->aPersonFields) == 0) {
            $this->aPersonFields = self::DEF_DETAIL_PERSON_FIELDS;
        }

        $aParams = [
            'personFields' => implode(',', $this->aPersonFields),
        ];

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . '?' . http_build_query($aParams);
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::GET, $aHeader);

        $result = false;
        if ($strResponse !== false) {
            $result = GContact::fromJSON($strResponse, $this->aPersonFields);
        }
        return $result;
    }

    /**
     * Creates a new contact.
     * @link https://developers.google.com/people/api/rest/v1/people/createContact
     * @param GContact $oContact
     * @return GContact|false
     */
    public function createContact(GContact $oContact)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];

        if (count($this->aPersonFields) == 0) {
            $this->aPersonFields = self::DEF_DETAIL_PERSON_FIELDS;
        }
        $aParams = ['personFields' => implode(',', $this->getUpdatePersonFields())];

        $result = false;
        $data = json_encode($oContact);
        if ($data !== false) {
            $strURI = 'https://people.googleapis.com/v1/people:createContact/?' . http_build_query($aParams);
            $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::POST, $aHeader, $data);

            if ($strResponse !== false) {
                $result = GContact::fromJSON($strResponse, $this->aPersonFields);
            }
        }
        return $result;
    }

    /**
     * Updates an existing contact specified by resourceName.
     * To prevent the user from data loss, this request fails with an 400 response
     * code, if the contact has changed on the server, since it was loaded.
     * Reload the data from the server and make the changes again!
     * @link https://developers.google.com/people/api/rest/v1/people/updateContact
     * @param string $strResourceName
     * @param GContact $oContact
     * @return GContact|false
     */
    public function updateContact(string $strResourceName, GContact $oContact)
    {
        $aHeader = [
            $this->oClient->getAuthHeader(),
            'Content-Type: application/json',
        ];

        if (count($this->aPersonFields) == 0) {
            $this->aPersonFields = self::DEF_DETAIL_PERSON_FIELDS;
        }
        $aParams = ['updatePersonFields' => implode(',', $this->getUpdatePersonFields())];

        $result = false;
        $data = json_encode($oContact);
        if ($data !== false) {
            $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . ':updateContact/?' . http_build_query($aParams);
            $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::PATCH, $aHeader, $data);

            if ($strResponse !== false) {
                $result = GContact::fromJSON($strResponse, $this->aPersonFields);
            }
        }
        return $result;
    }

    /**
     * Delete the requested contact.
     * @link https://developers.google.com/people/api/rest/v1/people/deleteContact
     * @param string $strResourceName
     * @return bool
     */
    public function deleteContact(string $strResourceName) : bool
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . ':deleteContact';
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::DELETE, $aHeader);
        return ($strResponse !== false);
    }

    /**
     * Set or unset the 'starred' mark for the specified contact.
     * The 'starred' mark just means, the contact belongs to the system group `contactGroups/starred`.
     * @see GContactGroups::addContactsToGroup()
     * @see GContactGroups::removeContactsFromGroup()
     * @param string $strResourceName
     * @param bool $bSetStarred
     * @return bool
     */
    public function setContactStarred(string $strResourceName, bool $bSetStarred = true) : bool
    {
        $oContactGroups = new GContactGroups($this->oClient);
        if ($bSetStarred) {
            $result = $oContactGroups->addContactsToGroup(GContactGroups::GRP_STARRED, [$strResourceName]);
        } else {
            $result = $oContactGroups->removeContactsFromGroup(GContactGroups::GRP_STARRED, [$strResourceName]);
        }
        return $result !== false;
    }


    /**
     * Set contact photo from image file.
     * Supported types are JPG, PNG, GIF and BMP.
     * @link https://developers.google.com/people/api/rest/v1/people/updateContactPhoto
     * @param string $strResourceName
     * @param string $strFilename
     * @return bool
     */
    public function setContactPhotoFile(string $strResourceName, string $strFilename) : bool
    {
        $blobPhoto = '';
        if (filter_var($strFilename, FILTER_VALIDATE_URL)) {
            $blobPhoto = $this->loadImageFromURL($strFilename);
        } elseif (file_exists($strFilename)) {
            $blobPhoto = $this->loadImageFromFile($strFilename);
        } else {
            $this->oClient->setError(0, 'File not found: ' . $strFilename, 'INVALID_ARGUMENT');
        }
        $result = false;
        if (!empty($blobPhoto)) {
            $aHeader = [
                $this->oClient->getAuthHeader(),
                'Content-Type: application/json',
            ];
            $data = json_encode(['photoBytes' => $blobPhoto]);
            if ($data !== false) {
                $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . ':updateContactPhoto';
                $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::PATCH, $aHeader, $data);
                $result = ($strResponse !== false);
            }
        }
        return $result;
    }

    /**
     * Set contact photo from base64 encoded image data.
     * @link https://developers.google.com/people/api/rest/v1/people/updateContactPhoto
     * @param string $strResourceName
     * @param string $blobPhoto  base64 encoded image
     * @return bool
     */
    public function setContactPhoto(string $strResourceName, string $blobPhoto) : bool
    {
        $result = false;
        // check for valid base64 encoded imagedata
        $img = base64_decode($blobPhoto);
        if ($img !== false && imagecreatefromstring(base64_decode($blobPhoto)) !== false) {
            $aHeader = [
                $this->oClient->getAuthHeader(),
                'Content-Type: application/json',
            ];
            $data = json_encode(['photoBytes' => $blobPhoto]);
            if ($data !== false) {
                $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . ':updateContactPhoto';
                $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::PATCH, $aHeader, $data);
                $result = ($strResponse !== false);
            }
        } else {
            $this->oClient->setError(0, 'Invalid base64 encoded image data', 'INVALID_ARGUMENT');
        }
        return $result;
    }

    /**
     * Delete contact photo for given contact.
     * @link https://developers.google.com/people/api/rest/v1/people/deleteContactPhoto
     * @param string $strResourceName
     * @return bool
     */
    public function deleteContactPhoto(string $strResourceName) : bool
    {
        $aHeader = [$this->oClient->getAuthHeader()];

        $strURI = 'https://people.googleapis.com/v1/' . $strResourceName . ':deleteContactPhoto';
        $strResponse = $this->oClient->fetchJsonResponse($strURI, GClient::DELETE, $aHeader);

        return ($strResponse !== false);
    }

    /**
     * Get updateable personFields.
     * @return array<string>
     */
    private function getUpdatePersonFields() : array
    {
        $aReadonlyPersonFields = [
            GContact::PF_PHOTOS,
            GContact::PF_COVER_PHOTOS,
            GContact::PF_AGE_RANGES,
            GContact::PF_METADATA,
        ];
        $aUpdatePersonFields = $this->aPersonFields;
        foreach ($aReadonlyPersonFields as $strReadonly) {
            if (($key = array_search($strReadonly, $aUpdatePersonFields)) !== false) {
                unset($aUpdatePersonFields[$key]);
            }
        }
        return $aUpdatePersonFields;
    }

    /**
     * Load an image from an URL.
     * The method uses curl to be independet of [allow_url_fopen] enabled on the system.
     * @param string $strURL
     * @return string   base64 encoded imagedata
     */
    private function loadImageFromURL(string $strURL) : string
    {
        $blobPhoto = '';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $strURL);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $strResponse = curl_exec($curl);

        $iResponseCode = intval(curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        $iHeaderSize = intval(curl_getinfo($curl, CURLINFO_HEADER_SIZE));

        curl_close($curl);

        if ($iResponseCode == 200 && is_string($strResponse)) {
            $aHeader = $this->oClient->parseHttpHeader(substr($strResponse, 0, $iHeaderSize));
            $strContentType = $aHeader['content-type'] ?? '';
            switch ($strContentType) {
                case 'image/jpeg':
                case 'image/png':
                case 'image/gif':
                case 'image/bmp':
                    $img = substr($strResponse, $iHeaderSize);
                    $blobPhoto = base64_encode($img);
                    break;
                default:
                    $this->oClient->setError(0, 'Unsupported file type: ' . $strContentType, 'INVALID_ARGUMENT');
                    break;
            }
        } else {
            $this->oClient->setError(0, 'File not found: ' . $strURL, 'INVALID_ARGUMENT');
        }
        return $blobPhoto;
    }

    /**
     * Load an image from an file.
     * In most cases an uploaded imagefile.
     * @param string $strFilename
     * @return string   base64 encoded imagedata
     */
    private function loadImageFromFile(string $strFilename) : string
    {
        $blobPhoto = '';
        $iImageType = exif_imagetype($strFilename);
        switch ($iImageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_PNG:
            case IMAGETYPE_GIF:
            case IMAGETYPE_BMP:
                $img = file_get_contents($strFilename);
                if ($img !== false) {
                    $blobPhoto = base64_encode($img);
                }
                break;
            default:
                $this->oClient->setError(0, 'Unsupported image type: ' . image_type_to_mime_type($iImageType), 'INVALID_ARGUMENT');
                break;
        }
        return $blobPhoto;
    }
}
