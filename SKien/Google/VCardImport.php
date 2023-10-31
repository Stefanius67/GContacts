<?php
declare(strict_types=1);

namespace SKien\Google;

use SKien\VCard\VCard;

/**
 * Import of a VCard file.
 *
 * Categories from the VCard are converted into contact groups for the Google
 * contacts. Since the `resourceName` of the group(s) is required when creating
 * a contact, an internal list (`groupName` => `resourceName`) of the available
 * groups is loaded before the VCard is read. <br/>
 * Categories (-> groups) that do not exist will be created and added to the
 * internal list.
 *
 * For a better overview of all imported contacts, the class offers the possibility
 * to create a new group to which all imported contacts are assigned (default name
 * is "VCard Import <<timestamp>>" - this can be changed with the setImportGroup()
 * method).
 *
 * > Since a VCard file can contain multiple contacts, the initial idea was to
 * > use the 'batchCreateContacts()' API function for creation. However, it is
 * > unfortunately not possible to transfer a photo/portrait directly when
 * > creating contacts, all the photos contained in the VCard file would have
 * > to be saved temporarily and subsequently assigned to the newly created
 * > contacts.  And doing so, the assignment of the images to the newly created
 * > 'personResourceName' will be a another hurdle. <br/>
 * > For my purposes the focus does not lie on importing multiple contacts from
 * > VCard data, so I've decided not to use 'batchCreateContacts()'. When
 * > creating the contacts sequentialy each by using a 'createContacts()' - API
 * > call, a photo can be assigned directly after the creation using the returned
 * > 'personResourceName'
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class VCardImport
{
    /** create a new contact group where all imported contacts are assigned to     */
    public const OPT_CREATE_IMPORT_GROUP = 0x0001;

    /** @var GClient the client we need for the import     */
    protected GClient $oClient;
    /** @var array<string,string>  array of available google contact groups groupName -> groupResourcename   */
    protected array $aGroupResourcenames = [];
    /** @var int options for the import     */
    protected int $iOptions = 0;
    /** @var string name of the importgroup to create and assign the new contacts     */
    protected string $strImportGroup = '';
    /** @var string resourceName of the last imported contact     */
    protected string $strLastPersonResource = '';
    /** @var int count of contacts beeing imported     */
    protected int $iImportCount = 0;

    /**
     * Create an instance of the class.
     */
    public function __construct(GClient &$oClient, int $iOptions = 0)
    {
        $this->oClient = $oClient;
        $this->iOptions = $iOptions;

        if (($iOptions & self::OPT_CREATE_IMPORT_GROUP) !== 0) {
            $this->strImportGroup = 'VCard Import ' . date('d.m.Y H:i');
        }
    }

    /**
     * Read the given VCard file and import the contact(s).
     * @param string $strFilename
     * @return bool
     */
    public function importVCard(string $strFilename) : bool
    {
        $result = false;
        $oVCard = new VCard();

        // since the API requests are JSON encoded, UTF-8 is the only choice...
        VCard::setEncoding('UTF-8');
        $iContacts = $oVCard->read($strFilename);
        if ($iContacts > 0 && $this->loadContactGroups()) {
            $oContacts = new GContacts($this->oClient);
            $oContacts->addPersonFields(GContact::PF_NAMES);
            $result = true;
            for ($i = 0; $i < $iContacts; $i++) {
                // create google contact from VCard, add groupmembership
                $oVCContact = $oVCard->getContact($i);
                $oContact = GContactFromVCard::fromVCardContact($oVCContact);
                if (!empty($this->strImportGroup)) {
                    $oContact->addGroupMembership($this->getGroupResourcename($this->strImportGroup));
                }
                $iCatCount = $oVCContact->getCategoriesCount();
                for ($iCat = 0; $iCat < $iCatCount; $iCat++) {
                    $oContact->addGroupMembership($this->getGroupResourcename($oVCContact->getCategory($iCat)));
                }
                // save the contact
                $oContact = $oContacts->createContact($oContact);
                if ($oContact === false) {
                    $result = false;
                    break;
                }
                // and set in the VCard contact contained portrait
                if (($blobPortrait = $oVCContact->getPortraitBlob()) !== '') {
                    if (($iPos = strpos($blobPortrait, 'base64,')) !== false) {
                        $blobPortrait = substr($blobPortrait, $iPos + 7);
                    }
                    if ($oContacts->setContactPhoto($oContact->getResourceName(), $blobPortrait) === false) {
                        $result = false;
                        break;
                    }
                }
                $this->iImportCount++;
                $this->strLastPersonResource = $oContact->getResourceName();
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getLastPersonResource() : string
    {
        return $this->strLastPersonResource;
    }

    /**
     * @return int
     */
    public function getImportCount() : int
    {
        return $this->iImportCount;
    }

    /**
     * @return string
     */
    public function getImportGroup() : string
    {
        return $this->strImportGroup;
    }

    /**
     * @param string $strImportGroup
     */
    public function setImportGroup(string $strImportGroup) : void
    {
        $this->strImportGroup = $strImportGroup;
    }

    /**
     * Load available contact groups.
     * @return bool
     */
    private function loadContactGroups() : bool
    {
        $oGroups = new GContactGroups($this->oClient);
        $aGroups = $oGroups->list(GContactGroups::GT_ALL_CONTACT_GROUPS, GContactGroups::RES_LIST);
        if ($aGroups !== false) {
            // result of GContactGroups::list() contains pairs `resourceName` => `groupName`
            // for our assignment, we need it vice versa
            $this->aGroupResourcenames = array_flip($aGroups);
        }
        return ($aGroups !== false);
    }

    /**
     * Get the resourcename of a given contact group.
     * If the requested group doesn't exist so far, it will be created and added
     * to the internal group list for later use.
     * @param string $strGroupName
     * @return string
     */
    private function getGroupResourcename(string $strGroupName) : string
    {
        if (isset($this->aGroupResourcenames[$strGroupName])) {
            return $this->aGroupResourcenames[$strGroupName];
        }
        // doesn't exist so far - just create the group and add  it to the list
        $oGroups = new GContactGroups($this->oClient);
        $strGroupResourcename = '';
        $aGroup = $oGroups->createGroup($strGroupName);
        if ($aGroup !== false) {
            $strGroupResourcename = $aGroup['resourceName'];
            $this->aGroupResourcenames[$strGroupName] = $strGroupResourcename;
        }
        return $strGroupResourcename;
    }
}