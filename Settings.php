<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\PushNotificator;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property string $Secret
 * @property string $ServerKey
 * @property bool $AllowCustomData
 * @property bool $DebugOutput
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module"
            ),
            "Secret" => new SettingsProperty(
                "",
                "string",
                null,
                "The secret key for external services that triggers notifications"
            ),
            "ServerKey" => new SettingsProperty(
                "",
                "string",
                null,
                "Cloud Messaging server key "
            ),
            "AllowCustomData" => new SettingsProperty(
                false,
                "bool",
                null,
                ""
            ),
            "DebugOutput" => new SettingsProperty(
                false,
                "bool",
                null,
                ""
            ),
        ];
    }
}
