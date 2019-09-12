<?php
namespace Sak;
class CTrigger extends CCrud
{
    public static function getFunctionLastValues($arr_functionid_itemid, $lastchange){
        $arr_functionid_value = [];
        foreach($arr_functionid_itemid as $functionid => $itemid){
            $arr_functionid_value[$functionid] = CItem::getItemLastValueFromDate($itemid, $lastchange);
        }

        return $arr_functionid_value;
    }

    public static function getById($triggerid)
    {
        $query = "SELECT * FROM triggers WHERE triggerid = " . $triggerid;
        $stmtExpression = ZDB::prepare($query);
        $stmtExpression->execute([]);
        return $stmtExpression->fetchAll(\PDO::FETCH_ASSOC)[0];
    }

    public static function getItemIdsByFunctionIds($function_ids){
        $query = "SELECT functionid, itemid FROM functions WHERE functionid IN (" . implode(",", $function_ids) .")";
        $stmt = ZDB::prepare($query);
        $stmt->execute([]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function getItemIdByFunctionId($functionid){
        $query = "SELECT itemid FROM functions WHERE functionid = :functionid";
        $stmt = ZDB::prepare($query);
        $stmt->execute(["functionid" => $functionid]);
        $res = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if(isset($res[0])){
            return $res[0];
        }
        return false;
    }

    public static function getTrueItemIdsByTrueFunctionIds($arr_functionid_itemid, $true_function_ids){
        if(empty($true_function_ids)){
            return false;
        }
        $true_function_ids_key_functionid = array_flip($true_function_ids);
        $true_item_ids = [];
        foreach($arr_functionid_itemid as $functionid => $itemid){
            if(isset($true_function_ids_key_functionid[$functionid])){}
            $true_item_ids[] = $itemid;
        }
        return $true_item_ids;
    }
}