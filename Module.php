<?php
namespace Aurora\Modules\PushNotificator;

class Module extends \Aurora\System\Module\AbstractModule
{
	public function init()
	{
		$this->subscribeEvent('Mail::BeforeDeleteAccount', array($this, 'onBeforeDeleteMailAccount'));
		\Aurora\System\Router::getInstance()->register($this->GetName(), 'push', [$this, 'onPushRoute']);
	}

	protected function checkSecret($sSecret)
	{
		$sSettingsSecret = $this->getConfig('Secret', '');
		if (!(!empty($sSettingsSecret) && $sSettingsSecret === $sSecret))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
		}
	}

	/**
	 *
	 */
	public function SetPushToken($Uid, $Token, $Emails)
	{
		$mResult = false;
		\Aurora\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oUser = \Aurora\Api::getAuthenticatedUser();
		if ($oUser)
		{
				$aPushTokens = (new \Aurora\System\EAV\Query(Classes\PushToken::class))
					->select()
					->where(
						[
							'Token' => $Token,
							'Uid' => $Uid
						]
					)
					->exec();
				foreach ($aPushTokens as $oPushToken)
				{
					if ($oPushToken instanceof Classes\PushToken)
					{
						$oPushToken->delete();
					}
				}
				foreach ($Emails as $sEmail)
				{
					$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($sEmail, $oUser->EntityId);
					if ($oAccount && $oAccount->IdUser === $oUser->EntityId)
					{
						$oPushToken = new Classes\PushToken();
						$oPushToken->IdAccount = $oAccount->EntityId;
						$oPushToken->Email = $oAccount->Email;
						$oPushToken->Uid = $Uid;
						$oPushToken->Token = $Token;

						$mResult = $oPushToken->save();
					}
				}
		}

		return $mResult;
	}

	/**
	 *
	 */
	public function SendPush($Secret, $Email, $Data)
	{
		$mResult = [];
		$this->checkSecret($Secret);

		$aPushTokens = (new \Aurora\System\EAV\Query(Classes\PushToken::class))
			->select()
			->where(
				[
					'$AND' => [
						'Email' => $Email,
					]
				]
			)
			->exec();

		$sUrl = 'https://fcm.googleapis.com/fcm/send';
		$sServerKey = $this->getConfig('ServerKey');

		$aRequestHeaders = [
			'Content-Type: application/json',
			'Authorization: key=' . $sServerKey,
		];

		foreach ($aPushTokens as $oPushToken)
		{
			$aRequestBody = [
				'to' => $oPushToken->Token,
				'data' => $Data,
			];
			$aFields = json_encode($aRequestBody);

			$ch = curl_init();
			curl_setopt_array($ch,
				[
					CURLOPT_URL => $sUrl,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_HTTPHEADER => $aRequestHeaders,
					CURLOPT_POSTFIELDS => $aFields,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true
				]
			);

			$mResult[$oPushToken->Token] = \json_decode(curl_exec($ch), true);
			curl_close($ch);
		}

		\Aurora\System\Api::LogObject($mResult, \Aurora\System\Enums\LogLevel::Full, 'push-');

		return $mResult;
	}

	public function onBeforeDeleteMailAccount($aArgs, &$mResult)
	{
		$oAccount = $aArgs['Account'];
		$oUser = $aArgs['User'];
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User
				&& $oAccount instanceof \Aurora\Modules\Mail\Classes\Account
				&& $oAccount->Email === $oUser->PublicId
			)
		{
			$aPushTokens = (new \Aurora\System\EAV\Query(Classes\PushToken::class))
			->select()
			->where(
				[
					'$AND' => [
						'IdAccount' => $oAccount->EntityId,
					]
				]
			)
			->exec();

			foreach ($aPushTokens as $oPushToken)
			{
				$oPushToken->delete();
			}
		}
	}

	public function onPushRoute()
	{
		$sSecret = isset($_GET['secret']) ? $_GET['secret'] : '';
		$sEmail = isset($_GET['email']) ? $_GET['email'] : '';
		$aData = isset($_GET['data']) ? \json_decode($_GET['data'], true) : '';
		$sPath = isset($_GET['path']) ? $_GET['path'] : '';

		if (!empty($sSecret))
		{
			if (!empty($sEmail) && !empty($aData))
			{
				echo \json_encode($this->Decorator()->SendPush($sSecret, $sEmail, $aData));
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
			else
			{
				echo 'Invalid arguments';
			}
		}
	}
}
