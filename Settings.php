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
 * @property string $ProjectNumber
 * @property string $FirebaseServiceAccountPath
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
            "ProjectNumber" => new SettingsProperty(
                "",
                "string",
                null,
                "The canonical identifier of your Firebase project"
            ),
            "FirebaseServiceAccountPath" => new SettingsProperty(
                "",
                "string",
                null,
                "Path to your Firebase service account key file relative to the Aurora's data directory"
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
