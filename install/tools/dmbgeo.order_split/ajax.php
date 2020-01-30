<? include($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('dmbgeo.order_split');

if(isset($_POST['EVENT_CHANGE_IBLOCK_ID']) && $_POST['EVENT_CHANGE_IBLOCK_ID']==true){
    echo json_encode(OrderSplit::getIblockSections($_POST['IBLOCK_ID']));
}

?>
