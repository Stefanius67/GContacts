<?php
declare(strict_types=1);

namespace SKien\Google;

/**
 * This class represents one single contact/person within the google contacts.
 *
 * @link https://developers.google.com/people/api/rest/v1/people#Person
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 *
 * @extends \ArrayObject<string, mixed>
 */
class GContact extends \ArrayObject
{
    /** Values for the personFields/readMask    */
    /** array of all available addresses
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Address    */
    public const PF_ADDRESSES = 'addresses';
    /** array of the persons age ranges: one of the self::AR_xxx constants
     *  @link https://developers.google.com/people/api/rest/v1/people#agerangetype    */
    public const PF_AGE_RANGES = 'ageRanges';
    /** array - singleton with the biographie (usually simply containes the notes to a person)
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Biography  */
    public const PF_BIOGRAPHIES = 'biographies';
    /** array - singleton containing the birthday
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Birthday  */
    public const PF_BIRTHDAYS = 'birthdays';
    /** array with a person's calendar URLs.
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.CalendarUrl    */
    public const PF_CALENDAR_URLS = 'calendarUrls';
    /** array with arbitrary client data that is populated by clients
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.ClientData     */
    public const PF_CLIENT_DATA = 'clientData';
    /** Array with a person's cover photos. A large image shown on the person's profile page
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.CoverPhoto     */
    public const PF_COVER_PHOTOS = 'coverPhotos';
    /** Array with a person's email addresses
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.EmailAddress     */
    public const PF_EMAIL_ADDRESSES = 'emailAddresses';
    /** Array with events related to the person
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Event     */
    public const PF_EVENTS = 'events';
    /** Array of identifiers from external entities related to the person
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.ExternalId     */
    public const PF_EXTERNAL_IDS = 'externalIds';
    /** Array - singleton contains the gender of the person (male, female, unspecified, userdefined)
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Gender     */
    public const PF_GENDERS = 'genders';
    /** Array with the person's instant messaging clients
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.ImClient     */
    public const PF_IM_CLIENTS = 'imClients';
    /** Array with the person's insterests
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Interest     */
    public const PF_INTERESTS = 'interests';
    /** Array with the person's locale preferences (IETF BCP 47 language tag)
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Locale     */
    public const PF_LOCALES = 'locales';
    /** Array with the person's locations
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Location     */
    public const PF_LOCATIONS = 'locations';
    /** Array containing the person's memberships to groups
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Membership     */
    public const PF_MEMBERSHIPS = 'memberships';
    /** The metadata about a person
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.PersonMetadata     */
    public const PF_METADATA = 'metadata';
    /** Array with miscellaneous keywords to the person
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.MiscKeyword     */
    public const PF_MISC_KEYWORDS = 'miscKeywords';
    /** Array - singleton containuing all names (except nicknames) of the person
      * @link https://developers.google.com/people/api/rest/v1/people#Person.Name      */
    public const PF_NAMES = 'names';
    /** Array with the person's nicknames
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Nickname     */
    public const PF_NICKNAMES = 'nicknames';
    /** Array with the person's occupations
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Occupation     */
    public const PF_OCCUPATIONS = 'occupations';
    /** Array with the person's past or current organizations. Overlapping date ranges are permitted
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Organization     */
    public const PF_ORGANIZATIONS = 'organizations';
    /** Array containing all of the person's phone numbers
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.PhoneNumber     */
    public const PF_PHONE_NUMBERS = 'phoneNumbers';
    /** Array with the person's photos. Pictures shown next to the person's name
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Photo     */
    public const PF_PHOTOS = 'photos';
    /** Array with the person's relations to another person.
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Relation     */
    public const PF_RELATIONS = 'relations';
    /** Array with a person's SIP addresses. Session Initial Protocol addresses are used for
     *  VoIP communications to make voice or video calls over the internet.
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.SipAddress     */
    public const PF_SIP_ADDRESSES = 'sipAddresses';
    /** Array containing the skills that the person has.
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Skill     */
    public const PF_SKILLS = 'skills';
    /** Array with the person's associated URLs. (Homepage, work, blog, ...)
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.Url     */
    public const PF_URLS = 'urls';
    /** Arbitrary user data that is populated by the end users
     *  @link https://developers.google.com/people/api/rest/v1/people#Person.UserDefined     */
    public const PF_USER_DEFINED = 'userDefined';

    /** Age range unspecified   */
    public const AR_UNSPECIFIED = 'AGE_RANGE_UNSPECIFIED';
    /** Age range: Younger than eighteen.   */
    public const AR_LESS_THAN_EIGHTEEN = 'LESS_THAN_EIGHTEEN';
    /** Age range: Between eighteen and twenty. */
    public const AR_EIGHTEEN_TO_TWENTY = 'EIGHTEEN_TO_TWENTY';
    /** Age range: Twenty-one and older.   */
    public const AR_TWENTY_ONE_OR_OLDER = 'TWENTY_ONE_OR_OLDER';

    /** Date type: string */
    public const DT_STRING = 0;
    /** Date type: unix timestamp */
    public const DT_UNIX_TIMESTAMP = 1;
    /** Date type: DateTime - Object */
    public const DT_OBJECT = 2;

    /**
     * @param array<mixed> $aData
     */
    public function __construct(array $aData = null)
    {
        if ($aData !== null) {
            $this->exchangeArray($aData);
        }
    }

    /**
     * Create a instance from a array.
     * @param array<mixed> $aData
     * @return GContact
     */
    static public function fromArray(array $aData) : GContact
    {
        return new GContact($aData);
    }

    /**
     * Create a instance from a fetched JSON contact.
     * @param string $strJSON
     * @param array<string> $aPersonFields
     * @return GContact
     */
    static public function fromJSON(string $strJSON, array $aPersonFields = null) : GContact
    {
        $oContact = self::createEmpty($aPersonFields);
        $aData = array_merge($oContact->getArrayCopy(), json_decode($strJSON, true));

        return new GContact($aData);
    }

    /**
     * Create a template object with an empty element for each requested personField - section.
     * Adds empty metadata to the object.
     * @param array<string> $aPersonFields
     * @return GContact
     */
    static public function createEmpty(array $aPersonFields = null) : GContact
    {
        $aFields = $aPersonFields ?? GContacts::DEF_DETAIL_PERSON_FIELDS;
        $aEmpty = [];
        foreach ($aFields as $strPersonFields) {
            $aEmpty[$strPersonFields] = [];
        }
        $aEmpty['metadata'] = [
            'sources' => [[
                'type' => 'CONTACT',
                'id' => '',
                'etag' => '',
            ]],
        ];
        return new GContact($aEmpty);
    }

    /**
     * @return string
     */
    public function getResourceName() : string
    {
        return $this['resourceName'] ?? '';
    }

    /**
     * @return string
     */
    public function getDisplayName() : string
    {
        $strDisplayName = '';
        if (isset($this['names']) && isset($this['names'][0])) {
            $strDisplayName = $this['names'][0]['displayName'] ?? '[unset]';
        }
        return $strDisplayName;
    }

    /**
     * Get last modification timestamp.
     * @param string $strTimezone   valid timezone
     * @return int unixtimestamp of last modification
     */
    public function getLastModified(string $strTimezone = null) : int
    {
        $uxtsLastModified = 0;
        if (isset($this['metadata']) && isset($this['metadata']['sources']) && isset($this['metadata']['sources'][0])) {
            $strLastModified = $this['metadata']['sources'][0]['updateTime'] ?? '';
        }
        if (!empty($strLastModified)) {
            $dtLastModified = new \DateTime($strLastModified);
            $dtLastModified->setTimezone(new \DateTimeZone($strTimezone ?? 'Europe/Berlin'));
            $uxtsLastModified = $dtLastModified->getTimestamp();
        }
        return $uxtsLastModified;
    }

    /**
     * Checks, if current contact belongs to requested contact group.
     * @param string $strGroupResourceName
     * @return bool
     */
    public function belongsToGroup(string $strGroupResourceName) : bool
    {
        $bIsMember = false;
        if (isset($this['memberships'])) {
            foreach ($this['memberships'] as $aMembership) {
                if (isset($aMembership['contactGroupMembership'])) {
                    $strResourceName = $aMembership['contactGroupMembership']['contactGroupResourceName'] ?? '';
                    if ($strResourceName == $strGroupResourceName) {
                        $bIsMember = true;
                        break;
                    }
                }
            }
        }
        return $bIsMember;
    }

    /**
     * Checks, if current contact is starred.
     * The property 'starred' just means, the contact belongs to the system
     * group ´contactGroups/starred´.
     * @return bool
     */
    public function isStarred() : bool
    {
        return $this->belongsToGroup(GContactGroups::GRP_STARRED);
    }

    /**
     * Set the meta data for the contact.
     * The server returns a 400 error if person.metadata.sources is not specified
     * for a contact to be updated or if there is no contact source.
     * The server returns a 400 error with reason "failedPrecondition" if
     * person.metadata.sources.etag is different than the contact's etag, which
     * indicates the contact has changed since its data was read. In this case,
     * clients should fetch the latest changes and merge their updates.
     * @param string $strType
     * @param string $strID
     * @param string $strETag
     */
    public function setMetaData(string $strType, string $strID, string $strETag) : void
    {
        if (!isset($this['metadata']['sources'])) {
            $this['metadata']['sources'] = [];
        }
        $this['metadata']['sources'][0] = [
            'type' => $strType,
            'id' => $strID,
            'etag' => $strETag,
        ];
    }

    /**
     * Set the primary item for the given personFields.
     * Reset the primary marker for all other items in the same array.
     * @link https://developers.google.com/people/api/rest/v1/people#fieldmetadata
     * @param string $strPersonFields
     * @param int $iPrimary
     */
    public function setPrimaryItem(string $strPersonFields, int $iPrimary) : void
    {
        foreach ($this[$strPersonFields] as $i => $aFields) {
            if (!isset($aFields['metadata'])) {
                $this[$strPersonFields][$i]['metadata'] = [];
            }
            $this[$strPersonFields][$i]['metadata']['sourcePrimary'] = ($i == $iPrimary);
        }
    }

    /**
     * Get the index of the primary item of the given personFields.
     * @link https://developers.google.com/people/api/rest/v1/people#fieldmetadata
     * @param string $strPersonFields
     * @return int $iPrimaryIndex   -1 if no items found or no item marked as primary
     */
    public function getPrimaryItemIndex(string $strPersonFields) : int
    {
        $iPrimary = -1;
        foreach ($this[$strPersonFields] as $i => $aFields) {
            if (isset($aFields['metadata'])) {
                $bPrimary = $this[$strPersonFields][$i]['metadata']['primary'] ?? false;
                if ($bPrimary) {
                    $iPrimary = $i;
                }
            }
        }
        return $iPrimary;
    }

    /**
     * Checks, if given item is marked as primary item.
     * @link https://developers.google.com/people/api/rest/v1/people#fieldmetadata
     * @param array<mixed> $aItem
     * @return bool
     */
    public function isPrimaryItem(array $aItem) : bool
    {
        return (isset($aItem['metadata']) && isset($aItem['metadata']['primary']) && $aItem['metadata']['primary'] == true);
    }

    /**
     * Set date of birth.
     * @param string|int|\DateTime $DateOfBirth    can be string (format YYYY-MM-DD), int (unixtimestamp) or DateTime - object
     */
    public function setDateOfBirth($DateOfBirth) : void
    {
        $uxts = 0;
        if (!isset($this['birthdays'][0])) {
            $this['birthdays'][0] = [];
        }
        if (!isset($this['birthdays'][0]['date'])) {
            $this['birthdays'][0]['date'] = [];
        }
        if (is_object($DateOfBirth) && get_class($DateOfBirth) == 'DateTime') {
            // DateTime -object
            $uxts = $DateOfBirth->getTimestamp();
        } else if (is_string($DateOfBirth)) {
            $oBirth = new \DateTime($DateOfBirth);
            $uxts = $oBirth->getTimestamp();
        } else {
            $uxts = $DateOfBirth;
        }
        if ($uxts > 0) {
            $this['birthdays'][0]['date']['month'] = intval(date('m', $uxts));
            $this['birthdays'][0]['date']['day'] = intval(date('d', $uxts));
            $this['birthdays'][0]['date']['year'] = intval(date('Y', $uxts));
        }
    }

    /**
     * Get date of birth.
     * The return type can be specified in the `$iType`parameter: <ul>
     * <li><b> self::DT_STRING (default):</b> Date as String in f´the format set with `$strFormat`param (default = 'Y-m-d') </li>
     * <li><b> self::DT_UNIX_TIMESTAMP:</b> Date as unix timestamp</li>
     * <li><b> self::DT_OBJECT:</b> Date as DateTime object </li></ul>
     *
     * if the property is not set in the contact method returns: <ul>
     * <li><b> self::DT_STRING:</b> empty string </li>
     * <li><b> self::DT_UNIX_TIMESTAMP:</b> integer 0</li>
     * <li><b> self::DT_OBJECT:</b> null </li></ul>
     *
     * @link https://datatracker.ietf.org/doc/html/rfc2426#section-3.1.5
     * @link https://www.php.net/manual/en/datetime.format.php
     * @param int $iType    self::DT_STRING (default), self::DT_UNIX_TIMESTAMP or self::DT_OBJECT
     * @param string $strFormat Date format compliant to DateTime::format() (default 'Y-m-d')
     * @return string|int|\DateTime|null
     */
    public function getDateOfBirth(int $iType = self::DT_STRING, string $strFormat = 'Y-m-d')
    {
        $result = '';
        $uxtsBirth = 0;
        if (isset($this['birthdays'][0]['date'])) {
            $aDate = $this['birthdays'][0]['date'];
            $uxtsBirth = mktime(0, 0, 0, $aDate['month'], $aDate['day'], $aDate['year']);
        }
        if ($iType == self::DT_UNIX_TIMESTAMP) {
            $result = $uxtsBirth;
        } else if ($iType ==  self::DT_OBJECT) {
            $dtBirth = null;
            if ($uxtsBirth > 0) {
                $dtBirth = new \DateTime();
                $dtBirth->setTimestamp($uxtsBirth);
            }
            $result = $dtBirth;
        } else if ($uxtsBirth > 0) {
            $result = date($strFormat, $uxtsBirth);
        }
        return $result;
    }
}