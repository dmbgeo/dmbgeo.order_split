<?
namespace dmbgeo\orderSplit\EventHandlers;

use Bitrix\Main\Localization;

Localization\Loc::loadMessages(__FILE__);

class SplitOrderAdd
{
    public static function handler($orderId, $arFields)
    {   
     
        if( \OrderSplit::isEnable()){
            global $DB;
            $strSql="INSERT INTO `dmbgeo_order_split` (`ORDER_ID`) VALUES('".$orderId."') ";
            $DB->Query($strSql, false, "File: ".__FILE__."Line: ".__LINE__);
        }

        return true;
    }

}
