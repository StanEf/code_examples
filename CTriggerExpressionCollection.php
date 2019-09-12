<?php
namespace Sak;

class CTriggerExpressionCollection
{
    private $raw_logical_trees = [];
    private $function_ids = [];
    private $arr_functionid_itemid = [];

    public function getItemIdsBecauseOfWhichTriggerBecameActive($triggerid, $trigger_lastchange){
        $trigger_raw_logical_tree = $this->getLogicalTree($triggerid);
        $function_ids = $this->getFunctionIdsFromLogicalTree($triggerid);

        $arr_functionid_itemid = $this->getItemIdsByFunctionIds($function_ids);

        $arr_functionid_value = CTrigger::getFunctionLastValues($arr_functionid_itemid, $trigger_lastchange);
        $resolved_logical_tree = CTriggerExpression::resolveLogicalTreeAll($trigger_raw_logical_tree, $arr_functionid_value);
        $true_function_ids = CTriggerExpression::getTrueFunctionIdsFromResolveLogicalTree($resolved_logical_tree);
        $item_ids = CTrigger::getTrueItemIdsByTrueFunctionIds($arr_functionid_itemid, $true_function_ids);

        return $item_ids;
    }

    private function getLogicalTree($triggerid){
        if(isset($this->raw_logical_trees[$triggerid])){
            return $this->raw_logical_trees[$triggerid];
        }

        $trigger = CTrigger::getById($triggerid);
        $raw_logical_tree = CTriggerExpression::getRawLogicalTree($trigger["expression"]);
        $this->raw_logical_trees[$triggerid] = $raw_logical_tree;
        return $this->raw_logical_trees[$triggerid];
    }

    private function getFunctionIdsFromLogicalTree($triggerid){
        if(isset($this->function_ids[$triggerid])){
            return $this->function_ids[$triggerid];
        }

        $function_ids = [];
        CTriggerExpression::getFunctionIdsFromNodes($this->raw_logical_trees[$triggerid], $function_ids);
        $this->function_ids[$triggerid] = array_keys($function_ids);
        return $this->function_ids[$triggerid];
    }

    private function getItemIdsByFunctionIds($function_ids){
        $response_arr_functionid_itemid = [];
        foreach($function_ids as $functionid){
            if(!isset($this->arr_functionid_itemid[$functionid])){
                $this->arr_functionid_itemid[$functionid] = CTrigger::getItemIdByFunctionId($functionid);
            }
            $response_arr_functionid_itemid[$functionid] = $this->arr_functionid_itemid[$functionid];
        }
        return $response_arr_functionid_itemid;
    }
}
