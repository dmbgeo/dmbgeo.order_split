<?

class OrderSplit
{

    const MODULE_ID = 'dmbgeo.order_split';

    public static function isEnable()
    {
        if (\Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_MODULE_STATUS_' . SITE_ID) == 'Y') {
            return true;
        }
        return false;
    }

    public static function isAgentEnable()
    {
        if (\Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS') == 'Y') {
            return true;
        }
        return false;
    }

    public static function getAgentLimit()
    {
        return intval(\Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_LIMIT'));
    }

    public static function getIblockSections($IBLOCK_ID)
    {
        CModule::IncludeModule('iblock');
        $result = [];
        $arFilter = array('IBLOCK_ID' => $IBLOCK_ID, 'GLOBAL_ACTIVE' => 'Y');
        $arSelect = array('ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL', 'CODE');
        $rsSection = CIBlockSection::GetTreeList($arFilter, $arSelect);
        while ($arSection = $rsSection->Fetch()) {
            $result[] = $arSection;
        }
        return $result;
    }

    public static function getSitesIdsArray()
    {
        $ids = array();
        $rsSites = CSite::GetList($by = "sort", $order = "desc");
        while ($arSite = $rsSites->Fetch()) {
            $ids[] = $arSite;
        }

        return $ids;
    }

    public static function getSectionList($IBLOCK_ID, $SECTION_ID)
    {
        CModule::IncludeModule('iblock');
        $result = [];
        $arFilter = array('IBLOCK_ID' => $IBLOCK_ID, 'SECTION_ID' => $SECTION_ID, 'DEPTH_LEVEL' => 2, 'GLOBAL_ACTIVE' => 'Y');
        $arSelect = array('ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL', 'CODE');
        $rsSect = CIBlockSection::GetList(array('NAME' => 'ASC'), $arFilter, false, $arSelect);
        while ($arSect = $rsSect->GetNext()) {
            $result[] = $arSect;
        }
        return $result;
    }

    public static function getOptions()
    {
        $result['MODULE_STATUS'] = \Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_MODULE_STATUS_' . SITE_ID);
        $result['AGENT_STATUS'] = \Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_STATUS');
        $result['AGENT_INTERVAL'] = \Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_ORDER_AGENT_INTERVAL');
        $result['IBLOCK_ID'] = \Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_IBLOCK_ID_' . SITE_ID);
        $result['SECTIONS'] = explode(',', \Bitrix\Main\Config\Option::get(SELF::MODULE_ID, 'DMBGEO_ORDER_SPLIT_OPTION_SECTION_LIST_' . SITE_ID));
        // $result['SECTION_LIST'] = $result['SECTIONS'];
        $result['SECTION_LIST'] = array();
        foreach ($result['SECTIONS'] as $SECTION) {
            $result['SECTION_LIST'] = array_merge($result['SECTION_LIST'], SELF::getSectionList($result['IBLOCK_ID'], $SECTION));
        }

        return $result;
    }

    public static function agent()
    {
        global $DB;
        $limit = self::getAgentLimit();
        // $limit = 1;
        $orders = $DB->Query("SELECT * FROM `dmbgeo_order_split` WHERE `SUCCESS`= '0' ORDER BY `ID` ASC LIMIT " . $limit, true);
        while ($orderDB = $orders->Fetch()) {
            $result = $DB->Query("UPDATE `dmbgeo_order_split` SET `SUCCESS`= '1' WHERE `ID` = '" . $orderDB['ID'] . "'", true)->result;
            if ($result && \Bitrix\Main\Loader::includeModule('sale')) {

                $oldOrder = \CSaleOrder::GetByID($orderDB['ORDER_ID']);
                $oldBasket = \CSaleBasket::GetList(array(), array('ORDER_ID' => $orderDB['ORDER_ID']));
                $oldBasketItems = [];

                while ($oldItem = $oldBasket->Fetch()) {
                    $oldBasketItems[] = $oldItem;
                }
                $oldOrder['BASKET_ITEMS'] = $oldBasketItems;
                $dbOrderProps = \CSaleOrderPropsValue::GetList(
                    array("SORT" => "ASC"),
                    array("ORDER_ID" => intval($orderDB['ORDER_ID']))
                );

                while ($arOrderProps = $dbOrderProps->GetNext()) {
                    $oldOrder['ORDER_PROPS'][] = $arOrderProps;
                }

                $oldOrder = self::orderSuccess($oldOrder);
            }
        }
        return '\OrderSplit::agent();';
    }
    public static function orderSuccess(&$arFields)
    {

        \CModule::IncludeModule('iblock');
        \CModule::IncludeModule('sale');
        \CModule::IncludeModule('catalog');
        $options = self::getOptions();
        $order_split = false;
        $sections = array();
        $count = 0;
        $originalBasketCount = count($arFields['BASKET_ITEMS']);
        $basket_rab = $arFields['BASKET_ITEMS'];
        foreach ($options['SECTION_LIST'] as $SECTION_LIST_ITEM) {
            $SECTION_IDS[] = $SECTION_LIST_ITEM['ID'];
        }
        foreach ($arFields['BASKET_ITEMS'] as $key => $ITEM) {
            $PRODUCT_ID = $ITEM['PRODUCT_ID'];
            $PRODUCT_ID = self::getBaseID($ITEM['PRODUCT_ID']);
            $PRODUCT_ID = $PRODUCT_ID ? $PRODUCT_ID : $ITEM['PRODUCT_ID'];
            $PAMAR = self::getElementParam($PRODUCT_ID);

            if ($PAMAR) {
                $SECTION_ID = self::orderSplitSectionValid($SECTION_IDS, $PAMAR);
                if ($SECTION_ID) {
                    $sections[$SECTION_ID][] = $ITEM;
                    $count++;
                    unset($basket_rab[$key]);
                }
            }
        }

        $sectionsCount = count($sections);
        sort($basket_rab);
        $basket_rabCount = count($basket_rab);
        if ($basket_rabCount > 0) {

            $arFields['BASKET_ITEMS'] = $basket_rab;
            $arFields['PRICE'] = $arFields['PRICE_DELIVERY'];
            foreach ($arFields['BASKET_ITEMS'] as $ITEM) {
                $arFields['PRICE'] += $ITEM['PRICE'] * $ITEM['QUANTITY'];
            }
            $arFields['PRICE'] = ceil($arFields['PRICE']);
            foreach ($sections as $section) {
                self::createNewOrder($arFields, $section);
            }
        } elseif ($sectionsCount > 1) {
            $arFields['BASKET_ITEMS'] = array_shift($sections);
            $arFields['PRICE'] = $arFields['PRICE_DELIVERY'];
            foreach ($arFields['BASKET_ITEMS'] as $ITEM) {
                $arFields['PRICE'] += $ITEM['PRICE'] * $ITEM['QUANTITY'];
            }
            $arFields['PRICE'] = ceil($arFields['PRICE']);
            foreach ($sections as $section) {
                self::createNewOrder($arFields, $section);
            }

        }
        if ($arFields['ID']) {
            $qwery = array(
                "PRICE" => $arFields['PRICE'],
            );
            \CSaleOrder::Update($arFields['ID'], $qwery);
        }
        return true;
    }

    public static function createNewOrder($arFields, $ITEMS)
    {

        global $DB;
        $arFields['ORDER_SPLIT'] = true;
        $arFields['PRICE_DELIVERY'] = 0;
        $arFields['BASKET_ITEMS'] = $ITEMS;
        $arFields['TAX_VALUE'] = '0';
        $arFields['PRICE'] = $arFields['PRICE_DELIVERY'];
        foreach ($ITEMS as $ITEM) {
            $arFields['PRICE'] += $ITEM['PRICE'] * IntVal($ITEM['QUANTITY']);
        }

        $arFields['PRICE'] = ceil($arFields['PRICE']);

        $qwery['LID'] = $arFields['LID'] ?? "";
        $qwery['PERSON_TYPE_ID'] = $arFields['PERSON_TYPE_ID'] ?? "";
        $qwery['PAYED'] = $arFields['PAYED'] ?? "";
        $qwery['DATE_PAYED'] = $arFields['DATE_PAYED'] ?? "";
        $qwery['EMP_PAYED_ID'] = $arFields['EMP_PAYED_ID'] ?? "";
        $qwery['CANCELED'] = $arFields['CANCELED'] ?? "";
        $qwery['DATE_CANCELED'] = $arFields['DATE_CANCELED'] ?? "";
        $qwery['EMP_CANCELED_ID'] = $arFields['EMP_CANCELED_ID'] ?? "";
        $qwery['REASON_CANCELED'] = $arFields['REASON_CANCELED'] ?? "";
        $qwery['STATUS_ID'] = $arFields['STATUS_ID'] ?? "";
        $qwery['EMP_STATUS_ID'] = $arFields['EMP_STATUS_ID'] ?? "";
        $qwery['PRICE_DELIVERY'] = $arFields['PRICE_DELIVERY'] ?? "";
        $qwery['ALLOW_DELIVERY'] = $arFields['ALLOW_DELIVERY'] ?? "";
        $qwery['DATE_ALLOW_DELIVERY'] = $arFields['DATE_ALLOW_DELIVERY'] ?? "";
        $qwery['EMP_ALLOW_DELIVERY_ID'] = $arFields['EMP_ALLOW_DELIVERY_ID'] ?? "";
        $qwery['PRICE'] = $arFields['PRICE'] ?? "";
        $qwery['CURRENCY'] = $arFields['CURRENCY'] ?? "";
        $qwery['DISCOUNT_VALUE'] = $arFields['DISCOUNT_VALUE'] ?? "";
        $qwery['USER_ID'] = $arFields['USER_ID'] ?? "";
        $qwery['PAY_SYSTEM_ID'] = $arFields['PAY_SYSTEM_ID'] ?? "";
        $qwery['DELIVERY_ID'] = $arFields['DELIVERY_ID'] ?? "";
        $qwery['USER_DESCRIPTION'] = $arFields['USER_DESCRIPTION'] ?? "";
        $qwery['ADDITIONAL_INFO'] = $arFields['ADDITIONAL_INFO'] ?? "";
        $qwery['COMMENTS'] = $arFields['COMMENTS'] ?? "";
        $qwery['TAX_VALUE'] = $arFields['TAX_VALUE'] ?? "";
        $qwery['AFFILIATE_ID'] = $arFields['AFFILIATE_ID'] ?? "";
        $qwery['STAT_GID'] = $arFields['STAT_GID'] ?? "";
        $qwery['PS_STATUS'] = $arFields['PS_STATUS'] ?? "";
        $qwery['PS_STATUS_CODE'] = $arFields['PS_STATUS_CODE'] ?? "";
        $qwery['PS_STATUS_DESCRIPTION'] = $arFields['PS_STATUS_DESCRIPTION'] ?? "";
        $qwery['PS_STATUS_MESSAGE'] = $arFields['PS_STATUS_MESSAGE'] ?? "";
        $qwery['PS_SUM'] = $arFields['PS_SUM'] ?? "";
        $qwery['PS_CURRENCY'] = $arFields['PS_CURRENCY'] ?? "";
        $qwery['PS_RESPONSE_DATE'] = $arFields['PS_RESPONSE_DATE'] ?? "";
        $qwery['SUM_PAID'] = $arFields['SUM_PAID'] ?? "";
        $qwery['PAY_VOUCHER_NUM'] = $arFields['PAY_VOUCHER_NUM'] ?? "";
        $qwery['PAY_VOUCHER_DATE'] = $arFields['PAY_VOUCHER_DATE'] ?? "";
        $qwery['STORE_ID'] = $arFields['STORE_ID'] ?? "";
        foreach ($qwery as $key => $val) {
            if ($qwery[$key] === false || $qwery[$key] === null || $qwery[$key] === "") {
                unset($qwery[$key]);
            }
        }
        // runkit_constant_redefine("CACHED_b_sale_order",false);

        $ORDER_ID = \CSaleOrder::Add($qwery);

        if ($ORDER_ID) {
            $DB->Query("DELETE FROM `dmbgeo_order_split` WHERE `ORDER_ID` = '" . $ORDER_ID . "'", true);

            foreach ($ITEMS as $ITEM) {
                if (isset($ITEM['ID'])) {
                    $params = array(
                        "ORDER_ID" => $ORDER_ID,
                    );

                    \CSaleBasket::Update($ITEM['ID'], $params);
                }
            }
            foreach ($arFields['ORDER_PROPS'] as $PROP) {
                $arFields = array(
                    "ORDER_ID" => $ORDER_ID,
                    "ORDER_PROPS_ID" => $PROP['ORDER_PROPS_ID'],
                    "NAME" => $PROP['NAME'],
                    "CODE" => $PROP['CODE'],
                    "VALUE" => $PROP['VALUE'],
                );
                \CSaleOrderPropsValue::Add($arFields);
            }

            return true;
        }

        return false;
    }

    public static function getElementParam($PRODUCT_ID)
    {
        $SECTION_ID = 0;
        $arSelect = array("ID", "NAME", "IBLOCK_SECTION_ID", 'IBLOCK_ID');
        $arFilter = array("ID" => $PRODUCT_ID, "ACTIVE" => "Y");
        $res = \CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
        if ($arFields = $res->GetNext()) {
            $db_old_groups = \CIBlockElement::GetElementGroups($PRODUCT_ID);
            $ar_new_groups = array($NEW_GROUP_ID);
            while ($ar_group = $db_old_groups->Fetch()) {
                $arFields['SECTIONS'][] = $ar_group["ID"];
            }

            return $arFields;
        }

        return false;
    }

    public static function orderSplitSectionValid($SECTIONS, $PARAM)
    {
        foreach ($PARAM['SECTIONS'] as $SECTION) {
            $nav = \CIBlockSection::GetNavChain($PARAM['IBLOCK_ID'], $SECTION);
            while ($arSectionPath = $nav->GetNext()) {
                if (in_array($arSectionPath['ID'], $SECTIONS)) {
                    return $arSectionPath['ID'];
                }
            }
        }
        return 0;
    }

    public static function getBaseID($ELEMENT_ID)
    {

        $ID = \CCatalogSku::GetProductInfo($ELEMENT_ID);
        if (is_array($ID)) {
            return $ID['ID'];
        }
        return 0;
    }

}
