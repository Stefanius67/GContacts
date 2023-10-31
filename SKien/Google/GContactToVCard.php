<?php
declare(strict_types=1);

namespace SKien\Google;

use SKien\VCard\VCard;
use SKien\VCard\VCardAddress;
use SKien\VCard\VCardContact;

/**
 * Class to convert a Google contact to a vcard.
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GContactToVCard extends VCardContact
{
    /** @var int  options to control the export     */
    protected int $iOptions = 0;
    /** @var array<string,string>   group maping resourceName -> groupName     */
    protected array $aGroups = [];
    /** @var string surrogate category name to use for 'starred' contacts
     *              (-> contacts belonging to the predefined system group 'starred')     */
    protected string $strStarredCategory = '';

    /**
     * Constructor.
     * @param int $iOptions
     */
    protected function __construct(array $aGroupNames, int $iOptions)
    {
        $this->aGroups = $aGroupNames;
        $this->iOptions = $iOptions;
    }

    /**
     * Sets the category to use for 'starred' contacts.
     * @param string $strStarredCategory
     */
    public function setStarredCategory(string $strStarredCategory) : void
    {
        $this->strStarredCategory = $strStarredCategory;
    }

    /**
     * Load data from given Google contact.
     * @param GContact $oGContact
     * @return bool
     */
    public function loadGContact(GContact $oGContact) : bool
    {
        // GContact::PF_NAMES is defined as singleton so it must exist exactly one time
        if (isset($oGContact[GContact::PF_NAMES]) && is_array($oGContact[GContact::PF_NAMES]) && count($oGContact[GContact::PF_NAMES]) == 1) {
            // PF_NAMES is always a singleton
            $this->strLastName = $oGContact[GContact::PF_NAMES][0]['familyName'] ?? '';
            $this->strFirstName = $oGContact[GContact::PF_NAMES][0]['givenName'] ?? '';
            $this->strPrefix = $oGContact[GContact::PF_NAMES][0]['honorificPrefix'] ?? '';
            $this->strSuffix = $oGContact[GContact::PF_NAMES][0]['honorificSuffix'] ?? '';
        } else {
            return false;
        }

        $this->readAddresses($oGContact);
        $this->readPhoneNumbers($oGContact);
        $this->readEmailAddresses($oGContact);
        $this->readHomepages($oGContact);
        $this->readOrganization($oGContact);
        $this->readMiscellaneous($oGContact);
        $this->readPhoto($oGContact);

        return true;
    }

    /**
     * Reads the address data.
     * Values taken from the GContact:
     * - type
     * - streetAddress
     * - postalCode
     * - city
     * - POBox
     * - ext. address
     * - country / countryCode
     * @param GContact $oGContact
     */
    protected function readAddresses(GContact $oGContact) : void
    {
        if (isset($oGContact[GContact::PF_ADDRESSES]) && is_array($oGContact[GContact::PF_ADDRESSES])) {
            foreach ($oGContact[GContact::PF_ADDRESSES] as $aAddress) {
                $oAddress = new VCardAddress();
                $oAddress->setType($this->getAddressTypeForVCard($aAddress['type'] ?? ''));
                $oAddress->setStr($aAddress['streetAddress'] ?? '');
                $oAddress->setPostcode($aAddress['postalCode'] ?? '');
                $oAddress->setCity($aAddress['city'] ?? '');
                $oAddress->setPOBox($aAddress['poBox'] ?? '');
                $oAddress->setExtAddress($aAddress['extendedAddress'] ?? '');
                if (isset($aAddress['country'])) {
                    $oAddress->setCountry($aAddress['country']);
                } else {
                    $oAddress->setCountry($aAddress['countryCode'] ?? '');
                }
                $this->addAddress($oAddress, $oGContact->isPrimaryItem($aAddress));
            }
        }
    }

    /**
     * Reads phone numbers.
     * @param GContact $oGContact
     */
    protected function readPhoneNumbers(GContact $oGContact) : void
    {
        if (isset($oGContact[GContact::PF_PHONE_NUMBERS]) && is_array($oGContact[GContact::PF_PHONE_NUMBERS])) {
            foreach ($oGContact[GContact::PF_PHONE_NUMBERS] as $aPhone) {
                $strType = $this->getPhoneTypeForVCard($aPhone['type'] ?? '');
                $this->addPhone($aPhone['value'] ?? '', $strType, $oGContact->isPrimaryItem($aPhone));
            }
        }
    }

    /**
     * Reads email addresses.
     * Since the used VCard library don't support the different possible types of
     * email addresses (home, work, blog,...), we ignore the type
     * @param GContact $oGContact
     */
    protected function readEmailAddresses(GContact $oGContact) : void
    {
        if (isset($oGContact[GContact::PF_EMAIL_ADDRESSES]) && is_array($oGContact[GContact::PF_EMAIL_ADDRESSES])) {
            foreach ($oGContact[GContact::PF_EMAIL_ADDRESSES] as $aMail) {
                $this->addEMail($aMail['value'] ?? '', $oGContact->isPrimaryItem($aMail));
            }
        }
    }

    /**
     * Reads homepage/url data.
     * Since the used VCard library don't support the different possible types of
     * homepages (home, work, blog,...), we ignore the type.
     * @param GContact $oGContact
     */
    protected function readHomepages(GContact $oGContact) : void
    {
        if (isset($oGContact[GContact::PF_URLS]) && is_array($oGContact[GContact::PF_URLS])) {
            foreach ($oGContact[GContact::PF_URLS] as $aURL) {
                $this->addHomepage($aURL['value'] ?? '');
            }
        }
    }

    /**
     * Reads organization related data.
     * @param GContact $oGContact
     */
    protected function readOrganization(GContact $oGContact) : void
    {
        if (isset($oGContact[GContact::PF_ORGANIZATIONS]) && is_array($oGContact[GContact::PF_ORGANIZATIONS]) && count($oGContact[GContact::PF_ORGANIZATIONS]) > 0 ) {
            // use first org. contained...
            $aOrg = $oGContact[GContact::PF_ORGANIZATIONS][0];

            $this->setOrganisation($aOrg['name'] ?? '');
            $this->setPosition($aOrg['title'] ?? '');
            $this->setSection($aOrg['department'] ?? '');
        }
    }

    /**
     * Reads photo from GContact.
     * If any custom photo is set, the first of that is used for the portrait.
     * If no custom photo found, the first conaitned deafult photo (created by google) is
     * set as VCard portrait, if configured in the options.
     * @param GContact $oGContact
     */
    protected function readPhoto(GContact $oGContact) : void
    {
        if (($this->iOptions & VCardExport::OPT_EXPORT_PHOTO) !== 0) {
            if (isset($oGContact[GContact::PF_PHOTOS]) && is_array($oGContact[GContact::PF_PHOTOS])) {
                // in the first loop we search for the first custom set photo...
                foreach ($oGContact[GContact::PF_PHOTOS] as $aPhoto) {
                    if (isset($aPhoto['url']) && (!isset($aPhoto['default']) || $aPhoto['default'] == false)) {
                        $this->setPortraitFile($aPhoto['url']);
                        return;
                    }
                }
                // ... and if configured, we look in a second loop for the first default created photo...
                if (($this->iOptions & VCardExport::OPT_USE_DEFAULT_PHOTO) !== 0) {
                    foreach ($oGContact[GContact::PF_PHOTOS] as $aPhoto) {
                        if (isset($aPhoto['url'])) {
                            $this->setPortraitFile($aPhoto['url']);
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Reads the group membership as VCard categories.
     * Only works, if group mapping is available or at least the 'starred' membership
     * should be mapped to a category.
     * @param GContact $oGContact
     */
    protected function readGroups(GContact $oGContact) : void
    {
        if (count($this->aGroups) > 0 || !empty($this->strStarredCategory)) {
            if (isset($oGContact[GContact::PF_MEMBERSHIPS]) && is_array($oGContact[GContact::PF_MEMBERSHIPS])) {
                foreach ($oGContact[GContact::PF_MEMBERSHIPS] as $aMembership) {
                    if (isset($aMembership['contactGroupMembership'])) {
                        $strResourceName = $aMembership['contactGroupMembership']['contactGroupResourceName'] ?? '';
                        if (!empty($this->strStarredCategory) && $strResourceName == 'contactGroups/starred') {
                            $this->addCategory($this->strStarredCategory);
                        } else if (isset($this->aGroups[$strResourceName])) {
                            $this->addCategory($this->aGroups[$strResourceName]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reads miscealaneous data and set it to the VCard contact.
     * - nickname
     * - biography -> note
     * - occupations -> role
     * - date of birth
     * - gender
     * @param GContact $oGContact
     */
    protected function readMiscellaneous(GContact $oGContact) : void
    {
        $this->setNickName($oGContact[GContact::PF_NICKNAMES][0]['value'] ?? '');
        $this->setNote($oGContact[GContact::PF_BIOGRAPHIES][0]['value'] ?? '');
        $this->setRole($oGContact[GContact::PF_OCCUPATIONS][0]['value'] ?? '');
        if (($uxtsDoB = $oGContact->getDateOfBirth(GContact::DT_UNIX_TIMESTAMP)) > 0) {
            $this->setDateOfBirth($uxtsDoB);
        }
        $this->setGender($oGContact[GContact::PF_GENDERS][0]['value'] ?? '');
    }

    /**
     * Get the VCard address type from the GContact address type.
     * 'home' and 'work' is converted to 'HOME' and 'WORK', for 'other'
     * the VCard type is left empty.
     * @param string $strGType
     * @return string
     */
    private function getAddressTypeForVCard($strGType) : string
    {
        $strType = '';
        if (strpos(strtolower($strGType), 'home') !== false) {
            $strType = 'HOME';
        } else if (strpos(strtoupper($strGType), 'work') !== false) {
            $strType = 'WORK';
        }
        return $strType;
    }

    /**
     * Get the VCard phone type from the GContact phone type.
     * 'home', 'mobile' and 'work' is converted to 'HOME', 'CELL' and 'WORK', all
     * other types are set to 'VOICE'.
     * @param string $strVCType
     * @return string
     */
    private function getPhoneTypeForVCard(string $strGType) : string
    {
        $strType = VCard::VOICE;
        if (strpos(strtolower($strGType), 'home') !== false) {
            $strType = VCard::HOME;
        } else if (strpos(strtolower($strGType), 'mobile') !== false) {
            $strType = VCard::CELL;
        } else if (strpos(strtolower($strGType), 'work') !== false) {
            $strType = VCard::WORK;
        }
        return $strType;
    }
}