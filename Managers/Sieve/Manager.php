<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\PushNotificator\Managers\Sieve;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2024, Afterlogic Corp.
 */
class Manager extends \Aurora\Modules\Mail\Managers\Sieve\Manager
{
    /**
     * @var object
     */
    protected $oModuleSettings;

    /**
     * @var string
     */
    protected $sSectionName = "PushNotifications";

    /**
     * @param \Aurora\Modules\PushNotificator\Settings $oSettings
     */
    public function __construct($oSettings)
    {
        parent::__construct(\Aurora\Modules\Mail\Module::getInstance());

        $this->oModuleSettings = $oSettings;

        array_splice($this->aSectionsOrders, 0, 0, [$this->sSectionName]);
    }

    /**
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @return array
     */
    public function getRule($oAccount)
    {
        $this->_parseSectionsData($oAccount);
        $sData = $this->_getSectionData($this->sSectionName);

        // defining defailt values
        $aResult = [
            'Enabled' => false
        ];

        $aMatch = array();
        if (!empty($sData) && preg_match('/#data=([^\n]+)/', $sData, $aMatch) && isset($aMatch[1])) {
            $oData = \json_decode(\base64_decode($aMatch[1]), true);

            if ($oData) {
                $aResult['Enabled'] = isset($oData['Enabled']) ? $oData['Enabled'] : $aResult['Enabled'];
            }
        }

        return $aResult;
    }

    /**
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param boolean $bEnable
     * @param array $aData
     *
     * @return bool
     */
    public function setRule($oAccount, $bEnable = true, $aData = [])
    {
        $sData = '';

        $bSaved = false;

        // $sEncodedData = \base64_encode(json_encode($aData));
        // $sEncryptedData = '#data=' . $sEncodedData . "\n";

        $sData .= "execute :pipe \"" . $this->oModuleSettings->SieveScriptName . "\";\n";

        $this->_addRequirement($this->sSectionName, 'vnd.dovecot.execute');

        if (!$bEnable) {
            $sData = '#' . implode("\n#", explode("\n", $sData));
        }

        // $sData = $sEncryptedData . $sData;

        $this->_parseSectionsData($oAccount);
        $this->_setSectionData($this->sSectionName, $sData);

        if (self::AutoSave) {
            $bSaved = $this->_resaveSectionsData($oAccount);
        }

        return $bSaved;
    }
}
