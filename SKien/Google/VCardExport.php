<?php
declare(strict_types=1);

namespace SKien\Google;

use SKien\VCard\VCard;

/**
 * Export one or more GContacts to a VCard file.
 *
 * A single GContact, all members of a group or all contacts can be exported in
 * VCard format (v3.0).
 * > The group membership(s) of a contact is stored as category(s) in the VCard
 * > contact.
 * > If the google system groups should be taken into account, this hve to be
 * > activated explicitly.
 * > The treatment of the system group 'contactGroups/starred', which is used by
 * > Google for 'starred' contacts (i.e. for the 'favorites'), can be configured
 * > separately.
 *
 * Since the GContact contains only a reference (the `resourceName`) to the
 * group(s) it belongs to, an internal list (`resourceName` => `groupName`) of
 * the available groups is loaded once before the export is started.<br/>
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class VCardExport
{
    /** also map system groups to catagories */
    public const OPT_MAP_GROUPS_TO_CATEGORY = 0x0001;
    /** also map system groups to catagories */
    public const OPT_MAP_SYSTEM_GROUPS  = 0x0002;
    /** export contined photo as portrait */
    public const OPT_EXPORT_PHOTO = 0x0004;
    /** use default photo from google, if no custom photo is set */
    public const OPT_USE_DEFAULT_PHOTO  = 0x0008;

    /** @var GClient the client we need for the import     */
    protected GClient $oClient;
    /** @var array<string,string>  array of available google contact groups groupName -> groupResourcename   */
    protected array $aGroupNames = [];
    /** @var int options for the import     */
    protected int $iOptions = 0;
    /** @var string charset for the created file     */
    protected string $strCharset = 'UTF-8';
    /** @var string surrogate category name to use for 'starred' contacts
     *              (-> contacts belonging to the predefined system group 'starred')     */
    protected string $strStarredCategory = '';
    /** @var int count of contacts beeing imported     */
    protected int $iExportCount = 0;

    /**
     * Create an instance of the class.
     * @param GClient $oClient
     * @param int $iOptions
     */
    public function __construct(GClient &$oClient, int $iOptions = null)
    {
        $this->oClient = $oClient;
        $this->iOptions = $iOptions ?? self::OPT_EXPORT_PHOTO | self::OPT_MAP_GROUPS_TO_CATEGORY;
    }

    /**
     * Create the VCard file containing the specified contact(s).
     * If param `$strResourceName` specifies a contact group, all member
     * of this group are exported, otherwise the specifeid contact is used.
     * If `$strResourceName` is empty, all contacts are exported.
     * @param string $strResourceName resourcename of contact or group
     * @param string $strFilename
     * @return bool
     */
    public function exportVCard(string $strResourceName, string $strFilename) : bool
    {
        $result = false;

        $oContacts = new GContacts($this->oClient);
        $oContacts->addPersonFields(GContacts::DEF_DETAIL_PERSON_FIELDS);

        // load list or contact
        $aContactList = false;
        if (empty($strResourceName) || strpos($strResourceName, 'contactGroups/') === 0) {
            $aContactList = $oContacts->list(GContacts::SO_LAST_NAME_ASCENDING, $strResourceName);
        } else {
            // load specified contact
            $oContact = $oContacts->getContact($strResourceName);
            if ($oContact !== false) {
                $aContactList = [$oContact->getArrayCopy()];
            }
        }
        $this->iExportCount = 0;
        if ($aContactList !== false) {
            $result = true;
            $oVCard = new VCard();
            VCard::setEncoding($this->strCharset);

            foreach ($aContactList as $aContact) {
                $oGContact = GContact::fromArray($aContact);
                $oVCContact = new GContactToVCard($this->aGroupNames, $this->iOptions);

                $oVCContact->setStarredCategory($this->strStarredCategory);
                if ($oVCContact->loadGContact($oGContact)) {
                    $oVCard->addContact($oVCContact);

                    $this->iExportCount++;
                }
            }
            if ($this->iExportCount > 0) {
                // and write to file
                $oVCard->write($strFilename, (strlen($strFilename) > 0));
            }
        }
        return $result;
    }

    /**
     * @return int
     */
    public function getExportCount() : int
    {
        return $this->iExportCount;
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
     * Sets the charset to use for exportfile.
     * If the generated file is to be imported into another Googler account or e.g.
     * with Thunderbird, UTF-8 is the correct character set, MS Outlook expects
     * (at least in the German/European version) an ISO-1234 encoded file (otherwise
     * umlauts will not be imported correctly)
     * @param string $strCharset
     */
    public function setCharset(string $strCharset) : void
    {
        $this->strCharset = $strCharset;
    }

    /**
     * Load available contact groups.
     * @return bool
     */
    private function loadContactGroups() : bool
    {
        $bOK = true;
        if (($this->iOptions & self::OPT_MAP_GROUPS_TO_CATEGORY) != 0) {
            $oGroups = new GContactGroups($this->oClient);
            $aGroups = $oGroups->list(GContactGroups::GT_ALL_CONTACT_GROUPS, GContactGroups::RES_LIST);
            if ($aGroups !== false) {
                $this->aGroupResourcenames = $aGroups;
            }
            $bOK = ($aGroups !== false);
        }
        return $bOK;
    }
}