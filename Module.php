<?php
namespace Aurora\Modules\PushNotificator;

class Module extends \Aurora\System\Module\AbstractModule
{
	public function init()
	{
	}

	protected function checkSecret($sSecret)
	{
		$sSettingsSecret = $this->getConfig('Secret', '');
		if (!(!empty($sSettingsSecret) && $sSettingsSecret === $sSecret))
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::AccessDenied);
		}
	}

	public function SetPushToken($Email, $Uid, $Token)
	{
		$mResult = false;
		\Aurora\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);
		$oUser = \Aurora\Api::getAuthenticatedUser();
		if ($oUser)
		{
			$oAccount = \Aurora\Modules\Mail\Module::Decorator()->GetAccountByEmail($Email, $oUser->EntityId);
			if ($oAccount && $oAccount->IdUser === $oUser->EntityId)
			{
				$oPushToken = (new \Aurora\System\EAV\Query(Classes\PushToken::class))
					->select()
					->where(
						[
							'$AND' => [
								'IdAccount' => $oAccount->EntityId,
								'Uid' => $Uid
							]
						]
					)
					->one()
					->exec();
				if ($oPushToken)
				{
					$oPushToken->Token = $Token;
					$mResult = $oPushToken->saveAttribute('Token');
				}
				else
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

	public function SendPush($Secret, $Email, $Message)
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
				'data' => [
					'message' => $Message
				],
			];
			$aFields = json_encode($aRequestBody);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $sUrl);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_HTTPHEADER, $aRequestHeaders);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $aFields);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$mResult[] = curl_exec($ch);
			curl_close($ch);
		}

		return $mResult;
	}
}
