<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\PushNotificator;

use Aurora\Modules\PushNotificator\Models\PushToken;
use Aurora\System\Enums\UserRole;
use Aurora\Modules\Core\Models\User;
use Aurora\Modules\Mail\Models\MailAccount;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->AddEntry('push', 'onSendPushRoute');

        $this->subscribeEvent('Mail::BeforeDeleteAccount', array($this, 'onBeforeDeleteMailAccount'));
        $this->subscribeEvent('SendNotification', array($this, 'onSendNotification'));
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Checks if provided secret is valid.
     *
     * @param string $sSecret
     *
     * @throws \Aurora\System\Exceptions\ApiException
     */
    protected function checkSecret($sSecret)
    {
        $sSettingsSecret = $this->oModuleSettings->Secret;
        if (!(!empty($sSettingsSecret) && $sSettingsSecret === $sSecret)) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
        }
    }

    /**
     * Subscribtion that deletes tokens of the deleted account.
     */
    public function onBeforeDeleteMailAccount($aArgs, &$mResult)
    {
        $oAccount = $aArgs['Account'];
        $oUser = $aArgs['User'];
        if ($oUser instanceof User && $oAccount instanceof MailAccount && $oAccount->Email === $oUser->PublicId) {
            PushToken::where('IdAccount', $oAccount->Id)->delete();
        }
    }

    public function onSendNotification($aArgs, &$mResult)
    {
        if (is_array($aArgs) && count($aArgs) > 0) {

            $sUrl = 'https://fcm.googleapis.com/fcm/send';
            $sServerKey = $this->oModuleSettings->ServerKey;
            $dDebug = $this->oModuleSettings->DebugOutput;
            $dAllowCustomData = $this->oModuleSettings->AllowCustomData;

            $aRequestHeaders = [
                'Content-Type: application/json',
                // Cloud Messaging API (Legacy)
                'Authorization: key=' . $sServerKey,

                // Firebase Cloud Messaging API (V1)
                // 'Authorization: Bearer ya29.ElqKBGN2Ri_Uz...HnS_uNreA'
            ];
            foreach ($aArgs as $aDataItems) {
                if (isset($aDataItems['Email']) && isset($aDataItems['Data'])) {
                    $sEmail = $aDataItems['Email'];
                    $aData = $aDataItems['Data'];
                    if (\is_array($aData) && count($aData) > 0) {
                        $aPushTokens = PushToken::where('Email', $sEmail)->get();
                        if (count($aPushTokens) > 0) {
                            /** @var PushToken $oPushToken */
                            foreach ($aPushTokens as $oPushToken) {
                                foreach ($aData as $aDataItem) {
                                    $aRequestBody = [
                                        'to' => $oPushToken->Token,
                                        'data' => $aDataItem,
                                        //'content_available' => true,
                                        'apns' => [
                                            //"headers" => [
                                                //"apns-priority" => "5"
                                            //]
                                        ]
                                    ];

                                    if (false) {
                                        //data notifications
                                        $aRequestBody['content_available'] = true;
                                        // $aRequestBody['apns']['headers'] = [
                                        // "apns-priority" => "5"
                                        // ];
                                    } else {
                                        // alert notifications

                                        /* is not required for flutter
                                        // $aRequestBody['android'] = [
                                            // "notification" => [
                                                // 'To' => $aDataItem['To']
                                            // ]
                                        // ];

                                        // $aRequestBody['apns']['payload'] = [
                                            // 'To' => $aDataItem['To']
                                        // ];
                                        */

                                        $aRequestBody['notification'] = [
                                            'title' => $aDataItem['From'],
                                            'body' => $aDataItem['Subject']
                                        ];
                                        $aRequestBody['data']['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
                                    }

                                    if ($dAllowCustomData && isset($aDataItems['Custom'])) {
                                        foreach ($aDataItems['Custom'] as $propName => $propValue) {
                                            $aRequestBody[$propName] = $propValue;
                                        }
                                    }

                                    $aFields = json_encode($aRequestBody);

                                    if ($dDebug && isset($aDataItems['Debug']) && $aDataItems['Debug'] === true) {
                                        var_dump($aFields);
                                        exit;
                                    }

                                    $ch = curl_init();
                                    curl_setopt_array(
                                        $ch,
                                        [
                                            CURLOPT_URL => $sUrl,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_HTTPHEADER => $aRequestHeaders,
                                            CURLOPT_POSTFIELDS => $aFields,
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_FOLLOWLOCATION => true
                                        ]
                                    );

                                    $aPushResult = \json_decode(curl_exec($ch), true);
                                    $mResult[] = [
                                        "Email" => $sEmail,
                                        "Token" => $oPushToken->Token,
                                        "Response" => $aPushResult
                                    ];

                                    if ($aPushResult['failure'] == 1 && isset($aPushResult['results'][0]) && $aPushResult['results'][0]['error'] === 'NotRegistered') {
                                        $oPushToken->delete();
                                    }
                                    curl_close($ch);
                                }
                            }
                        } else {
                            $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sEmail);
                            if ($oUser) {
                                /** @var \Aurora\Modules\Mail\Module $oMailModule */
                                $oMailModule = \Aurora\System\Api::GetModule('Mail');
                                $oAccount = $oMailModule->GetAccountByEmail($sEmail, $oUser->Id);
                                if ($oAccount instanceof MailAccount) {
                                    try {
                                        $this->DisablePushNotification($oAccount->Id);
                                    } catch (\Exception $oEx) {
                                    } // skip throw exception - pipe farward may not exists
                                }
                            }
                        }
                    }
                }
            }
        }
        \Aurora\System\Api::Log("", \Aurora\System\Enums\LogLevel::Full, 'push-');
        \Aurora\System\Api::LogObject($mResult, \Aurora\System\Enums\LogLevel::Full, 'push-');
    }

    /**
     * An entry point that is a wrapper for the SendPush method.
     */
    public function onSendPushRoute()
    {
        $sSecret = isset($_GET['secret']) ? $_GET['secret'] : '';
        $aData = isset($_GET['data']) ? \json_decode($_GET['data'], true) : '';

        if (!empty($sSecret)) {
            if (!empty($aData)) {
                echo \json_encode($this->Decorator()->SendPush($sSecret, $aData));
            } else {
                echo 'Invalid arguments';
            }
        }
    }

    /**
     * Register device in DB to send push notifications
     *
     * @param string $Token
     * @param string $Uid
     * @param array $Users
     *
     * @return bool
     */
    public function SetPushToken($Uid, $Token, $Users)
    {
        $mResult = false;
        // TODO: why authentication is not required?
        \Aurora\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        $bAuthStatus = true;
        foreach ($Users as $aUser) {
            $oUser = \Aurora\Api::getAuthenticatedUser($aUser['AuthToken']);
            if (!$oUser) {
                $bAuthStatus = false;
                break;
            }
        }

        // TODO: $bAuthStatus can be true because $Users is empty list
        // then PushToken can be deleted by Uid
        if ($bAuthStatus) {
            $aPushTokens = PushToken::where('Uid', $Uid)->get();
            foreach ($aPushTokens as $oPushToken) {
                $oPushToken->delete();
            }
        }

        if (!empty($Token) && count($Users) > 0) {
            foreach ($Users as $aUser) {
                $oUser = \Aurora\Api::getAuthenticatedUser($aUser['AuthToken']);
                if ($oUser) {
                    $aEmails = $aUser['Emails'];
                    if (\is_array($aEmails) && count($aEmails) > 0) {
                        foreach ($aEmails as $sEmail) {
                            $oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($sEmail, $oUser->Id);
                            // TODO: check for access to account is not reqired because we get account of paricula user
                            if ($oAccount && $oAccount->IdUser === $oUser->Id) {
                                $oPushToken = PushToken::create([
                                    'IdUser' => $oUser->Id,
                                    'IdAccount' => $oAccount->Id,
                                    'Email' => $oAccount->Email,
                                    'Uid' => $Uid,
                                    'Token' => $Token
                                ]);

                                if ($oPushToken) {
                                    $mResult = $this->EnablePushNotification($oPushToken->IdAccount);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $mResult;
    }

    /**
     * Main method for sending push notifications
     *
     * @param string $Secret
     * @param array $Data
     *
     * @return mixed
     */
    public function SendPush($Secret, $Data)
    {
        \Aurora\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        $mResult = [];
        $this->checkSecret($Secret);

        if (is_array($Data) && count($Data) > 0) {
            $aArgs = $Data;

            $this->broadcastEvent(
                'SendNotification',
                $aArgs,
                $mResult
            );
        }

        return $mResult;
    }

    /**
     * Checks if push notifications are enabled for account
     *
     * @param int $AccountID
     *
     * @return bool
     */
    public function IsPushNotificationEnabled($AccountID)
    {
        \Aurora\Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $bResult = false;

        $oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccount($AccountID);
        $oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();

        if ($oAccount instanceof MailAccount && $oAccount->IdUser === $oAuthenticatedUser->Id) {
            $bResult = !!$oAccount->getExtendedProp($this->GetName() . '::NotificationsEnabled');
        }

        if ($bResult) {
            /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
            $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
            if ($oModule) {
                $bResult = !empty($oModule->GetScriptForward($AccountID));
            }
        }

        return $bResult;
    }

    /**
     * Disables push notifications for account
     *
     * @param int $AccountID
     *
     * @return bool
     */
    public function EnablePushNotification($AccountID)
    {
        \Aurora\Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $bResult = false;

        $oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccount($AccountID);
        $oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();

        if ($oAccount instanceof MailAccount && $oAccount->IdUser === $oAuthenticatedUser->Id) {
            $oAccount->setExtendedProp($this->GetName() . '::NotificationsEnabled', true);
            $bResult = !!$oAccount->save();
        }

        if ($bResult) {
            /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
            $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
            if ($oModule) {
                $bResult = $oModule->CreateScriptForward($AccountID);
            }
        }

        return $bResult;
    }

    /**
     * Disables push notifications for account
     *
     * @param int $AccountID
     *
     * @return bool
     */
    public function DisablePushNotification($AccountID)
    {
        \Aurora\Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $bResult = false;

        $oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccount($AccountID);
        $oAuthenticatedUser = \Aurora\Api::getAuthenticatedUser();

        if ($oAccount instanceof MailAccount && $oAccount->IdUser === $oAuthenticatedUser->Id) {
            $oAccount->setExtendedProp($this->GetName() . '::NotificationsEnabled', false);
            $bResult = !!$oAccount->save();
        }

        if ($bResult) {
            /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
            $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
            if ($oModule) {
                $bResult = $oModule->RemoveScriptForward($AccountID);
            }
        }

        return $bResult;
    }
}
