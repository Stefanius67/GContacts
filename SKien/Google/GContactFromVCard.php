<?php
declare(strict_types=1);

namespace SKien\Google;

use SKien\VCard\VCardContact;

/**
 * Class representing a GContact created from a VCard contact.
 * When creating a Google contact from a VCard contact, there are two important
 * points to keep in mind:
 *  1.  In order to use the categories from the VCard as group membership for the
 *      Google contact, the group name (...the category) must be converted to the
 *      Google group resource. In order to prevent the Google groups from being
 *      re-read for each contact when reading a VCard file with multiple contacts,
 *      this conversion and assignment must take place outside of this class before
 *      the contact is saved.
 *  2.  Because a contact's portrait/photo can't be set for a new contact until
 *      they've been assigned a resource name, this must be done as a second step
 *      after the contact is saved. In addition, setting a photo must be done via
 *      a separate API function anyway.
 *
 * > Due to the different structure of Google and VCard contacts and taking into
 * > account that the package used to read a VCard file does not support all VCard
 * > properties, data may be lost when reading in.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GContactFromVCard extends GContact
{
    /**
     * Protected constructor.
     * Only way to create an instance is to use the static method
     * VCardContact::fromVCardContact().
     * @param VCardContact $oVCContact
     */
    protected function __construct(VCardContact $oVCContact)
    {
        // names personFields must always exists as singleton
        parent::__construct([
            GContact::PF_NAMES => [[
                'honorificPrefix' => $oVCContact->getPrefix(),
                'familyName' => $oVCContact->getLastName(),
                'givenName' => $oVCContact->getFirstName(),
                'honorificSuffix' => $oVCContact->getSuffix(),
            ]],
        ]);

        $this->readAddresses($oVCContact);
        $this->readPhoneNumbers($oVCContact);
        $this->readEmailAddresses($oVCContact);
        $this->readHomepages($oVCContact);
        $this->readOrganization($oVCContact);
        $this->readMiscellaneous($oVCContact);
    }

    /**
     * The only way to create an instance of this class.
     * @param VCardContact $oVCContact
     * @return GContactFromVCard
     */
    static public function fromVCardContact(VCardContact $oVCContact) : GContactFromVCard
    {
        return new GContactFromVCard($oVCContact);
    }

    /**
     * Add a groupmembership.
     * For the membership we have to assign the group names (categories from VCard)
     * outside of this class to the according group resource name.
     * @param string $strGroupResourceName
     */
    public function addGroupMembership(string $strGroupResourceName) : void
    {
        if (!empty($strGroupResourceName)) {
            if (!isset($this[GContact::PF_MEMBERSHIPS])) {
                $this[GContact::PF_MEMBERSHIPS] = [];
            }
            $this[GContact::PF_MEMBERSHIPS][] = [
                'contactGroupMembership' => [
                    'contactGroupResourceName' => $strGroupResourceName
                ]
            ];
        }
    }

    /**
     * Read the address data from the VCard contact.
     * Values taken from the VCard:
     * - type:
     * - streetAddress
     * - postalCode
     * - city
     * - POBox
     * - ext. address
     * - country / countryCode
     * > Although according to the VCard specification the country is given with its
     * > full name, we interpret it as a country code (ISO 3166-1 alpha-2) if it is
     * > exactly 2 characters long!
     * @param VCardContact $oVCContact
     */
    protected function readAddresses(VCardContact $oVCContact) : void
    {
        $iCount = $oVCContact->getAddressCount();
        $iPrimary = -1;
        for ($i = 0; $i < $iCount; $i++) {
            $oAddress = $oVCContact->getAddress($i);
            if ($oAddress !== null) {
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, -1, 'type', $this->getAddressTypeFromVCard($oAddress->getType()));
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'streetAddress', $oAddress->getStr());
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'postalCode', $oAddress->getPostcode());
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'city', $oAddress->getCity());
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'poBox', $oAddress->getPOBox());
                $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'extendedAddress', $oAddress->getExtAddress());
                $strCountry = $oAddress->getCountry();
                if (strlen($strCountry) == 2) {
                    $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'countryCode', strtoupper($strCountry));
                } else {
                    $iIndex = $this->setValue(GContact::PF_ADDRESSES, $iIndex, 'country', $strCountry);
                }
                if ($iPrimary < 0 && $oAddress->isPreferred()) {
                    $iPrimary = $iIndex;
                }
            }
            if ($iPrimary >= 0) {
                $this->setPrimaryItem(self::PF_ADDRESSES, $iPrimary);
            }
        }
    }

    /**
     * Read phone numbers from the VCard contact.
     * @param VCardContact $oVCContact
     */
    protected function readPhoneNumbers(VCardContact $oVCContact) : void
    {
        $iCount = $oVCContact->getPhoneCount();
        $iPrimary = -1;
        for ($i = 0; $i < $iCount; $i++) {
            $aPhone = $oVCContact->getPhone($i);
            $iIndex = $this->setValue(GContact::PF_PHONE_NUMBERS, -1, 'value', $aPhone['strPhone']);
            $iIndex = $this->setValue(GContact::PF_PHONE_NUMBERS, $iIndex, 'type', $this->getPhoneTypeFromVCard($aPhone['strType']));
            if ($iPrimary < 0 && strpos(strtoupper($aPhone['strType']), 'PREF') !== false) {
                $iPrimary = $iIndex;
            }
        }
        if ($iPrimary >= 0) {
            $this->setPrimaryItem(self::PF_PHONE_NUMBERS, $iPrimary);
        }
    }

    /**
     * Read email addresses from the VCard contact.
     * Since the used VCard library don't support the different possible types of
     * email addresses (home, work, blog,...), we set each entry type to 'other'
     * @param VCardContact $oVCContact
     */
    protected function readEmailAddresses(VCardContact $oVCContact) : void
    {
        $iCount = $oVCContact->getEMailCount();
        for ($i = 0; $i < $iCount; $i++) {
            $iIndex = $this->setValue(GContact::PF_EMAIL_ADDRESSES, -1, 'value', $oVCContact->getEMail($i));
            $iIndex = $this->setValue(GContact::PF_EMAIL_ADDRESSES, $iIndex, 'type', 'other');
        }
    }

    /**
     * Read homepage/url data from the VCard contact.
     * Since the used VCard library don't support the different possible types of
     * hompeages (home, work, blog,...), we set each URL type to 'other'
     * @param VCardContact $oVCContact
     */
    protected function readHomepages(VCardContact $oVCContact) : void
    {
        $iCount = $oVCContact->getHomepageCount();
        for ($i = 0; $i < $iCount; $i++) {
            $iIndex = $this->setValue(GContact::PF_URLS, -1, 'value', $oVCContact->getHomepage($i));
            $iIndex = $this->setValue(GContact::PF_URLS, $iIndex, 'type', 'other');
        }
    }

    /**
     * Read organization related data from the VCard contact.
     * @param VCardContact $oVCContact
     */
    protected function readOrganization(VCardContact $oVCContact) : void
    {
        // informations about organization, title, ...
        $iOrg = $this->setValue(GContact::PF_ORGANIZATIONS, -1, 'name', $oVCContact->getOrganisation());
        $iOrg = $this->setValue(GContact::PF_ORGANIZATIONS, $iOrg, 'title', $oVCContact->getPosition());
        $iOrg = $this->setValue(GContact::PF_ORGANIZATIONS, $iOrg, 'department', $oVCContact->getSection());
    }

    /**
     * Read miscealaneous data from the VCard contact.
     * - nickname
     * - note -> biography
     * - role -> occupations
     * - date of birth
     * - gender
     * @param VCardContact $oVCContact
     */
    protected function readMiscellaneous(VCardContact $oVCContact) : void
    {
        $this->setValue(GContact::PF_NICKNAMES, -1, 'value', $oVCContact->getNickName());
        $this->setValue(GContact::PF_BIOGRAPHIES, -1, 'value', $oVCContact->getNote());
        $this->setValue(GContact::PF_OCCUPATIONS, -1, 'value', $oVCContact->getRole());

        if (($uxtsDoB = $oVCContact->getDateOfBirth(VCardContact::DT_UNIX_TIMESTAMP)) > 0) {    // @phpstan-ignore-line
            $this->setDateOfBirth($uxtsDoB);
        }
        if (($strGender = $oVCContact->getGender()) !== '') {
            $this->setValue(GContact::PF_GENDERS, -1, 'value', $this->getGenderFromVCard($strGender));
        }
    }

    /**
     * Set a value.
     * If the requested personfield does not exist yet, it will be created and
     * the personfield will be added to the internal list. All personfields in
     * a GContact contains an array (although some of them are singletons). <br/>
     * To add a new item, the requested index must be set to -1, the function
     * then returns the newly created index. Empty or unset values are ignored
     * and no new index is created in this case.
     * @param string $strPersonFields
     * @param int $iIndex
     * @param string $strField
     * @param string $strValue
     * @return int
     */
    private function setValue(string $strPersonFields, int $iIndex, string $strField, ?string $strValue) : int
    {
        if (!isset($strValue) || empty($strValue)) {
            return $iIndex;
        }
        if (!isset($this[$strPersonFields])) {
            $this[$strPersonFields] = [];
        }
        if ($iIndex >= count($this[$strPersonFields])) {
            // if index is specified, it must already exist!
            return $iIndex;
        }
        if ($iIndex < 0) {
            $this[$strPersonFields][] = [];
            $iIndex = count($this[$strPersonFields]) - 1;
        }
        $this[$strPersonFields][$iIndex][$strField] = $strValue;

        return $iIndex;
    }

    /**
     * Get the GContact phone type from the VCard phone type.
     * 'HOME', 'CELL' and 'WORK' is converted to 'home', 'mobile' and 'work', all
     * other types are set to 'other'.
     * @param string $strVCType
     * @return string
     */
    private function getPhoneTypeFromVCard(string $strVCType) : string
    {
        $strType = 'other';
        if (strpos(strtoupper($strVCType), 'HOME') !== false) {
            $strType = 'home';
        } else if (strpos(strtoupper($strVCType), 'CELL') !== false) {
            $strType = 'mobile';
        } else if (strpos(strtoupper($strVCType), 'WORK') !== false) {
            $strType = 'work';
        }
        return $strType;
    }

    /**
     * Get the GContact address type from the VCard address type.
     * 'HOME' and 'WORK' is converted to 'home' and 'work', all other types are
     * set to 'other'.
     * @param string $strVCType
     * @return string
     */
    private function getAddressTypeFromVCard($strVCType) : string
    {
        $strType = 'other';
        if (strpos(strtoupper($strVCType), 'HOME') !== false) {
            $strType = 'home';
        } else if (strpos(strtoupper($strVCType), 'WORK') !== false) {
            $strType = 'work';
        }
        return $strType;
    }

    /**
     * Get the GContact gender from the VCard gender.
     * VCard values:
     *  - M: "male"
     *  - F: "female"
     *  - O: "other"
     *  - N: "none or not applicable"
     *  - U: "unknown".
     * to GContact values:
     *  - male
     *  - female
     *  - unspecified
     * @param string $strVCGender
     * @return string
     */
    private function getGenderFromVCard($strVCGender) : string
    {
        $strGender = 'unspecified';
        if ($strVCGender == 'M') {
            $strGender = 'male';
        } else if ($strVCGender == 'F') {
            $strGender = 'female';
        }
        return $strGender;
    }
}
