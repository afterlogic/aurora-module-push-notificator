<?php

namespace Aurora\Modules\PushNotificator;

use Aurora\Modules\PushNotificator\Models\PushToken;

class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->subscribeEvent('Mail::BeforeDeleteAccount', array($this, 'onBeforeDeleteMailAccount'));
        \Aurora\System\Router::getInstance()->register($this->GetName(), 'push', [$this, 'onPushRoute']);
    }

    /**
     *
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     *
     * @return Settings
     */
    protected function GetModuleSettings()
    {
        return $this->oModuleSettings;
    }

    protected function checkSecret($sSecret)
    {
        $sSettingsSecret = $this->getConfig('Secret', '');
        if (!(!empty($sSettingsSecret) && $sSettingsSecret === $sSecret)) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
        }
    }

    /**
     *
     */
    public function SetPushToken($Uid, $Token, $Users)
    {
        $mResult = false;
        \Aurora\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);

        $bAuthStatus = true;
        foreach ($Users as $aUser) {
            $oUser = \Aurora\Api::getAuthenticatedUser($aUser['AuthToken']);
            if (!$oUser) {
                $bAuthStatus = false;
                break;
            }
        }

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
                            if ($oAccount && $oAccount->IdUser === $oUser->Id) {
                                // @phpstan-ignore-next-line
                                $oPushToken = PushToken::create([
                                    'IdUser' => $oUser->Id,
                                    'IdAccount'=> $oAccount->Id,
                                    'Email'=>$oAccount->Email,
                                    'Uid'=> $Uid,
                                    'Token'=>$Token
                                ]);

                                if ($mResult) {
                                    $this->EnablePushNotification($oPushToken->IdAccount);
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
     * @param string $Secret
     * @param array $Data
     *
     * @return mixed
     */
    public function SendPush($Secret, $Data)
    {
        $mResult = [];
        $this->checkSecret($Secret);

        if (is_array($Data) && count($Data) > 0) {
            $sUrl = 'https://fcm.googleapis.com/fcm/send';
            $sServerKey = $this->getConfig('ServerKey');
            $dDebug = $this->getConfig('DebugOutput');
            $dAllowCustomData = $this->getConfig('AllowCustomData');

            $aRequestHeaders = [
                'Content-Type: application/json',
                'Authorization: key=' . $sServerKey,
            ];
            foreach ($Data as $aDataItems) {
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
                                        'apns'=> [
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
                                    $mResult[$oPushToken->Token] = $aPushResult;

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
                                if ($oAccount instanceof \Aurora\Modules\Mail\Models\MailAccount) {
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
        \Aurora\System\Api::LogObject($mResult, \Aurora\System\Enums\LogLevel::Full, 'push-');

        return $mResult;
    }

    public function onBeforeDeleteMailAccount($aArgs, &$mResult)
    {
        $oAccount = $aArgs['Account'];
        $oUser = $aArgs['User'];
        if ($oUser instanceof \Aurora\Modules\Core\Models\User
                && $oAccount instanceof \Aurora\Modules\Mail\Models\MailAccount
                && $oAccount->Email === $oUser->PublicId
        ) {
            $aPushTokens = PushToken::where('IdAccount', $oAccount->Id)->delete();
        }
    }

    public function onPushRoute()
    {
        $sSecret = isset($_GET['secret']) ? $_GET['secret'] : '';
        // $sEmail = isset($_GET['email']) ? $_GET['email'] : '';
        $aData = isset($_GET['data']) ? \json_decode($_GET['data'], true) : '';
        $sPath = isset($_GET['path']) ? $_GET['path'] : '';

        if (!empty($sSecret)) {
            // if (!empty($sEmail) && !empty($aData)) {
            if (!empty($aData)) {
                echo \json_encode($this->Decorator()->SendPush($sSecret, $aData));
            }
            // else if (!empty($sPath))
            // {
            // 	$rEml = \fopen($sPath, 'r');
            // 	$oMessage = \ZBateson\MailMimeParser\Message::parse($rEml);
            // 	if ($oMessage)
            // 	{
            // 		$oTo = $oMessage->getHeader('To');
            // 		$sEmail = $oTo->getEmail();

            // 		$oFrom = $oMessage->getHeader('From');
            // 		$sFrom = $oFrom->getEmail();

            // 		$sSubject = $oMessage->getHeaderValue('Subject');
            // 		$aData = [
            // 			"From" => $sFrom,
            // 			"To" => $sEmail,
            // 			"Subject" => $sSubject
            // 		];

            // 		echo \json_encode($this->Decorator()->SendPush($sSecret, $sEmail, $aData));
            // 	}
            // }
            else {
                echo 'Invalid arguments';
            }
        }
    }

    public function IsPushNotificationEnabled($AccountID)
    {
        $bResult = false;

        /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
        $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
        if ($oModule) {
            $bResult = !empty($oModule->GetScriptForward($AccountID));
        }

        return $bResult;
    }

    public function EnablePushNotification($AccountID)
    {
        $bResult = false;

        /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
        $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
        if ($oModule) {
            $bResult = $oModule->CreateScriptForward($AccountID);
        }

        return $bResult;
    }

    public function DisablePushNotification($AccountID)
    {
        $bResult = false;

        /** @var \Aurora\Modules\CpanelIntegrator\Module $oModule */
        $oModule = \Aurora\System\Api::GetModuleDecorator('CpanelIntegrator');
        if ($oModule) {
            $bResult = $oModule->RemoveScriptForward($AccountID);
        }

        return $bResult;
    }
}
