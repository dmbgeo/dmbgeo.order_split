<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

$module_id = 'dmbgeo.order_split';
$module_path = str_ireplace($_SERVER["DOCUMENT_ROOT"], '', __DIR__) . $module_id . '/';
$ajax_path = '/bitrix/tools/' . $module_id . '/' . 'ajax.php';
CModule::IncludeModule('main');
CModule::IncludeModule($module_id);
CModule::IncludeModule('iblock');

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
Loc::loadMessages(__FILE__);
if ($APPLICATION->GetGroupRight($module_id) < "S") {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}


\OrderSplit::agent();
$request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();
//получим инфоблоки пользователей на сайте, чтоб добавить в настройки
$SITES = OrderSplit::getSitesIdsArray();
$arSections = [];
foreach ($SITES as $SITE) {
    $aTabs[] = array(
        'DIV' => $SITE['LID'],
        'TAB' => $SITE['NAME'],
        'OPTIONS' => array(
            array('DMBGEO_ORDER_SPLIT_OPTION_ORDER_MODULE_STATUS_' . $SITE['LID'], Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_ORDER_MODULE_STATUS'), '', array('checkbox', "Y")),
        ),
    );
    $params[] = 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_MODULE_STATUS_' . $SITE['LID'];
    $params[] = 'DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID_' . $SITE['LID'];
    $params[] = 'DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_' . $SITE['LID'];

}
$aTabs[] = array(
    'DIV' => "AGENT",
    'TAB' => Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT'),
    'OPTIONS' => array(
        array('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS', Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS'), '', array('checkbox', "Y")),
		array('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_INTERVAL', Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_INTERVAL'), '60', array('text', "0")),
		array('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_LIMIT', Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_LIMIT'), '20', array('text', "0")),
    ),
);
$params[] = 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS';
$params[] = 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_INTERVAL';
$params[] = 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_LIMIT';

if ($request->isPost() && $request['Apply'] && check_bitrix_sessid()) {
	
    foreach ($params as $param) {
        if (array_key_exists($param, $_POST) === true) {
            if ($param == 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS') {
                $oldParam = Option::get($module_id, $param);
                if ($oldParam != $_POST[$param]) {
                    if ($_POST[$param] == 'Y') {
                        createAgent($module_id, $_POST['DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_INTERVAL'] ?? 0);
                    }
                }
			}
            Option::set($module_id, $param, is_array($_POST[$param]) ? implode(",", $_POST[$param]) : $_POST[$param]);
		}
		else{
			if($param==="DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS"){
				deleteAgent($module_id);
			}
			Option::set($module_id, $param, "N");
		}
    }

}

function deleteAgent($module_id)
{
	\CAgent::RemoveModuleAgents($module_id);
}

function createAgent($module_id, $newInterval)
{	$interval = intval($newInterval);  
	$arFields= Array(
		
	);
    $result=\CAgent::AddAgent(
		'\OrderSplit::agent();', // имя функции
        $module_id, // идентификатор модуля
        "N", // агент не критичен к кол-ву запусков
        $interval, // интервал запуска - 1 сутки
        date("d.m.Y H:i:s",(time()+$interval)), // дата первой проверки - текущее
        "Y", // агент активен
        date("d.m.Y H:i:s",time()), // дата первого запуска - текущее
        1
	);


}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

?>
<?$tabControl->Begin();?>

<form method='post' action='<?echo $APPLICATION->GetCurPage() ?>?mid=<?=htmlspecialcharsbx($request['mid'])?>&amp;lang=<?=$request['lang']?>' name='DMBGEO_ORDER_SPLIT_settings'>

<? $n=count($aTabs); ?>
<?foreach ($aTabs as $key => $aTab):
    
    	if ($aTab['OPTIONS']): ?>
	
			<?$tabControl->BeginNextTab();?>
			
			<?
    		$DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID = COption::GetOptionString($module_id, 'DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID_' . $aTab['DIV']);
    		if (intval($DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID) > 0) {
    		    $DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST = COption::GetOptionString($module_id, 'DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_' . $aTab['DIV']);
    		    $DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST = explode(",", $DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST);
    		    $arSections = OrderSplit::getIblockSections($DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID);
    		}
    		?>

				<?__AdmSettingsDrawList($module_id, $aTab['OPTIONS']);?>
				<? if($n!==$key+1): ?>
					<tr>
						<td><?echo Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID') ?></td>
						<td><?echo GetIBlockDropDownListEx($DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID, 'DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_TYPE_ID_' . $aTab['DIV'], 'DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID_' . $aTab['DIV'], false, "DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_TYPE_ID('DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_".$aTab['DIV']."')", "DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID(this,'$ajax_path','DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_".$aTab['DIV']."')"); ?></td>
					</tr>
					<tr>
						<td class="adm-detail-valign-top adm-detail-content-cell-l" width="50%"><?echo Loc::getMessage('DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST'); ?><a name="opt_DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_<?=$aTab['DIV'];?>"></a></td>
						<td width="50%" class="adm-detail-content-cell-r">
							<select size="20" multiple="" id='DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_<?=$SITE['LID'];?>' name="DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_<?=$SITE['LID'];?>[]">
								<?if (intval($DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID) > 0): ?>

									<?foreach ($arSections as $arSection): ?>
									<?
    									$option = '';
    									$option .= '<option value="' . $arSection['ID'] . '"';
    									if (in_array($arSection['ID'], $DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST)) {
    									    $option .= ' selected="selected" ';
    									}
									
    									$option .= '>';
    									if (intval($arSection['DEPTH_LEVEL']) > 1) {
    									    for ($i = 0; $i < intval($arSection['DEPTH_LEVEL']); $i++) {
    									        $option .= '&nbsp;&nbsp;&nbsp;&nbsp;';
    									    }
    									}
    									$option .= $arSection['NAME'];
    									$option .= '</option>';
    									?>
											<?echo $option; ?>
									<?endforeach;?>
								<?endif;?>
							</select>
						</td>
					</tr>
									<?endif;?>

		
	
	<?endif?>;
<? endforeach;?>
	<?

$tabControl->Buttons();?>

	<input type="submit" name="Apply" value="<?echo GetMessage('MAIN_SAVE') ?>">
	<input type="reset" name="reset" value="<?echo GetMessage('MAIN_RESET') ?>">
	<?=bitrix_sessid_post();?>
</form>
<?$tabControl->End();?>
<?
CJSCore::Init(array("jquery"));
?>
<script>
function DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID(selector,PATH,selectorSection){
	var IBLOCK_ID=$(selector).val();

	$.post( PATH,{ IBLOCK_ID: $(selector).val() ,EVENT_CHANGE_IBLOCK_ID:true}).done(function( data ) {
		data=JSON.parse(data);
		$('#'+selectorSection).html('');
		data.forEach(function(section){
			var option='<option value="'+section['ID']+'" >';
				if(parseInt(section['DEPTH_LEVEL'])>1){
					for(var i=0 ; i < parseInt(section['DEPTH_LEVEL']) ; i++){
					option+='&nbsp;&nbsp;&nbsp;&nbsp;';
				}
			}
			option+=section['NAME'];
			option+='</option>';
			$('#'+selectorSection).append(option);
		});
  	});
}

function DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_TYPE_ID(selectorSection){
	$('#'+selectorSection).html('');
}


</script>

