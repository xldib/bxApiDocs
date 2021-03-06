<?
if(!CModule::IncludeModule('rest'))
	return;

class CIMRestService extends IRestService
{
	public static function OnRestServiceBuildDescription()
	{
		return array(
			'im' => array(
				'im.notify' => array('CIMRestService', 'notify'),
			),
		);
	}

	public static function notify($arParams)
	{
		global $USER;

		$arMessageFields = array(
			"TO_USER_ID" => $arParams['to'],
			"FROM_USER_ID" => $USER->GetID(),
			"NOTIFY_TYPE" => IM_NOTIFY_FROM,
			"NOTIFY_MODULE" => "rest",
			"NOTIFY_EVENT" => "rest_notify",// - get it from the oauth module
			"NOTIFY_MESSAGE" => $arParams['message'],
		);

		return CIMNotify::Add($arMessageFields);
	}
}

?>