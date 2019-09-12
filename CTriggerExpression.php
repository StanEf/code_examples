<?php
namespace Sak;

class CTriggerExpression
{

    public $trigger_expression = "";
    public $function_ids = [];
    public $raw_logical_tree = [];
    public $arr_itemid_value = [];


    public function __construct($trigger_expression, $lastvalue){
        $this->lastvalue = $lastvalue;
        $this->trigger_expression = $trigger_expression;
        $this->raw_logical_tree = self::getRawLogicalTree($this->trigger_expression);

        $function_ids = [];
        self::getFunctionIdsFromNodes($this->raw_logical_tree, $function_ids);

        $arr_functionid_itemid = self::getItemIdsByFunctionIds(array_keys($function_ids));

        $this->arr_functionid_value = self::getFunctionLastValues($arr_functionid_itemid, $this->lastvalue);

        self::resolveLogicalTreeAll($this->raw_logical_tree, $this->arr_functionid_value);
    }

    public static function getTrueFunctionIdsFromResolveLogicalTree($resolved_logical_tree){
        if(isset($resolved_logical_tree[0][0]["true_functionids"])){
            return $resolved_logical_tree[0][0]["true_functionids"];
        }
        if(isset($resolved_logical_tree[0][0]["functionid"]) || $resolved_logical_tree[0][0]["result"] == "true"){
            return [0 => $resolved_logical_tree[0][0]["functionid"]];
        }
    }

    private static function resolveLogicalTree2(&$parents, $parentid,  $arr_functionid_value){
        foreach($parents[$parentid] as &$node){
            $node_id = $node["id"];

            if(isset($node["node_type"])){
                if($node["node_type"] == "sign"){
                    $node["function_val"] = $arr_functionid_value[$node["functionid"]];
                    $node["result"] = self::checkInequality($node["function_val"], $node["inequality_val"], $node["inequality_sign"]);
                }
                if($node["node_type"] == "logic"){
                    self::resolveLogicalTree2($parents, $node_id, $arr_functionid_value);
                    $tmp = self::checkLogicalInequality($parents, $node_id, $node["logical_key"]);
                    if($tmp) {
                        $node["result"] = $tmp["result"];
                        if (isset($tmp["true_functionids"])) {
                            $node["true_functionids"] = $tmp["true_functionids"];
                        }
                    }
                }
            }
        }
    }

    private static function checkLogicalInequality(&$parents, $node_id, $logical_key){
        $response = [];
        if($logical_key === "&"){
            $logical_expression_correctness = "true";
            foreach($parents[$node_id] as $subnode){
                if(!isset($subnode["result"])){
                    return false;
                }

                if($subnode["result"] === "false"){
                    $logical_expression_correctness = "false";
                }else{
                    if(isset($subnode["functionid"])) {
                        $response["true_functionids"][] = $subnode["functionid"];
                    }

                    if(isset($subnode["true_functionids"])){
                        if(isset($response["true_functionids"])) {
                            $response["true_functionids"] = array_merge($subnode["true_functionids"], $response["true_functionids"]);
                        }else{
                            $response["true_functionids"] = $subnode["true_functionids"];
                        }
                    }
                }
            }
            $response["result"] = $logical_expression_correctness;

            if($logical_expression_correctness === "false"){
                unset($response["true_functionids"]);
            }
        }

        if($logical_key === "|"){
            $logical_expression_correctness = "false";
            foreach($parents[$node_id] as $subnode){
                if(!isset($subnode["result"])){
                    return false;
                }

                if($subnode["result"] === "true"){
                    $logical_expression_correctness = "true";
                    if(isset($subnode["functionid"])) {
                        $response["true_functionids"][] = $subnode["functionid"];
                    }
                    if(isset($subnode["true_functionids"])) {
                        if(isset($response["true_functionids"])) {
                            $response["true_functionids"] = array_merge($subnode["true_functionids"], $response["true_functionids"]);
                        }else{
                            $response["true_functionids"] = $subnode["true_functionids"];
                        }
                    }
                }
            }
            $response["result"] = $logical_expression_correctness;
        }
        return $response;
    }

    public static function resolveLogicalTreeAll($parents, $arr_functionid_value){
        self::resolveLogicalTree2($parents, 0, $arr_functionid_value);
        return $parents;
    }

    /**
     * если вначале выражения скобка ( то если эта скобка закрылась в конце
     * то удалить обе
     * @param $expression
     */
    private static function clearExpressionEdgeBrackets($expression){
        if($expression[0] == "("){
            $lenght = strlen($expression);
            $current_count_opening_bracket = 0;
            $delete_edge_brackets = false;
            for($i=0;$i<$lenght;$i++){
                $symbol = $expression[$i];
                if($symbol == '('){
                    $current_count_opening_bracket++;
                }
                if($symbol == ')'){
                    $current_count_opening_bracket--;
                }

                if($current_count_opening_bracket == 0 && $i != $lenght - 1){
                    // если первая скобка ( закрылась не в конце то возвращаем исходное выражение
                    return $expression;
                }

                if($i == ($lenght - 1) && $current_count_opening_bracket == 0 && $expression[0] === "(" && $expression[$lenght - 1] === ")"){
                    $delete_edge_brackets = true;
                }
            }
            if($delete_edge_brackets){
                $expression = substr($expression, 1, -1);
            }
        }
        return $expression;
    }

    public static function getFunctionIdsFromNodes($arr, &$function_ids){
        foreach($arr as $val){
            if(isset($val["node_type"])){
                if($val["node_type"] == "sign"){
                    $function_ids[$val["functionid"]] = 1;
                }
            }else if(is_array($val)){
                self::getFunctionIdsFromNodes($val, $function_ids);
            }
        }
    }

    public static function getRawLogicalTree($expression){
        $id_generator = 1;
        $parents = [];
        $parentid = 0;

        self::array_req3($expression, $id_generator, $parents, $parentid);
        return $parents;
    }

    private static function array_req3($expression_current_level, &$id_generator, &$parents, $parentid) {
        $expression = self::clearExpressionEdgeBrackets($expression_current_level);
        $arr_stub_deep = self::generateStubDeep($expression);
        $pattern_encapsulation_deep = $arr_stub_deep["pattern_numered_stubdeep"];

        // определяем логические символы на текущем уровне вложенности(все они должны быть одинаковы)
        preg_match_all("/[&|]+/", $pattern_encapsulation_deep, $current_logical_symbols);

        if(isset($current_logical_symbols[0]) && isset($current_logical_symbols[0][0]) && $current_logical_symbols[0][0]){
            $current_logical_symbol = $current_logical_symbols[0][0];
                $parents_node_tmp = [];
                $parents_node_tmp["node_type"] = "logic";
                $parents_node_tmp["logical_key"] = $current_logical_symbol;
                $parents_node_tmp["logical_parent_id"] = $parentid;
                $parents_node_tmp["id"] = $id_generator++;
                $parents[$parentid][] = $parents_node_tmp;

                $parentid = $parents_node_tmp["id"];

            $expression_encapsulate_deep = preg_replace("/\((.*)\)/", "STUB_DEEP", $expression);
            preg_match_all("/{[0-9]+}[><=]{1}[0-9.]+/", $expression_encapsulate_deep, $arr_nodes_type_sign);

            if(isset($arr_nodes_type_sign[0])) {
                foreach ($arr_nodes_type_sign[0] as $node_type_sign){
                    $tmp = [];
                    $tmp["node_type"] = "sign";
                    preg_match_all("/[><=]{1}/", $node_type_sign, $arr_sign);
                    $tmp["inequality_sign"] = $arr_sign[0][0];
                    preg_match_all("/{[0-9]+}/", $node_type_sign, $arr_functionid);

                    $tmp["functionid"] = trim($arr_functionid[0][0]);
                    $tmp["functionid"] = substr($tmp["functionid"], 1, -1);

                    preg_match_all("/{[0-9]+}[><=]{1}/", $node_type_sign, $arr_all_except_value);
                    $tmp["inequality_val"] = trim(str_replace($arr_all_except_value[0][0], "", $node_type_sign));
                    $tmp["id"] = $id_generator++;
                    $tmp["parentid"] = $parentid;

                    $parents[$tmp["parentid"]][] = $tmp;
                }
            }

            $arr_stub_deep = self::generateStubDeep($expression);

            if(!empty($arr_stub_deep["pattern_arr_stubdeep"])) { // если есть скобки ()
                foreach ($arr_stub_deep["pattern_arr_stubdeep"] as $subexpression) {
                    self::array_req3($subexpression, $id_generator, $parents, $parentid);
                }
            }
        }else{  // только строка вида {200200000013563}=1
            $tmp = [];
            $tmp["node_type"] = "sign";
            preg_match_all("/[><=]{1}/", $expression, $arr_sign);
            $tmp["inequality_sign"] = $arr_sign[0][0];
            preg_match_all("/{[0-9]+}/", $expression, $arr_functionid);

            $tmp["functionid"] = trim($arr_functionid[0][0]);
            $tmp["functionid"] = substr($tmp["functionid"], 1, -1);

            preg_match_all("/{[0-9]+}[><=]{1}/", $expression, $arr_all_except_value);
            $tmp["inequality_val"] = trim(str_replace($arr_all_except_value[0][0], "", $expression));
            $tmp["id"] = $id_generator++;
            $tmp["parentid"] = $parentid;

            $parents[$tmp["parentid"]][] = $tmp;
        }
    }

    private static function checkInequality($function_val, $inequality_val, $sign){
        if($sign === ">"){
            if((float)$function_val > (float)$inequality_val){
                return "true";
            }
        }
        if($sign === "<"){
            if((float)$function_val < (float)$inequality_val){
                return "true";
            }
        }
        if($sign === "="){
            if((float)$function_val === (float)$inequality_val){
                return "true";
            }
        }
        return "false";
    }

    /**
     * @param $function_ids
     * @return [functionid => itemid, ]
     */
    public static function getItemIdsByFunctionIds($function_ids){
        $query = "SELECT functionid, itemid FROM functions WHERE functionid IN (" . implode(",", $function_ids) .")";
        $stmt = ZDB::prepare($query);
        $stmt->execute([]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function getFunctionLastValues($arr_functionid_itemid, $lastchange){
        $arr_functionid_value = [];
        foreach($arr_functionid_itemid as $functionid => $itemid){
            $arr_functionid_value[$functionid] = CItem::getItemLastValueFromDate($itemid, $lastchange);
        }

        return $arr_functionid_value;
    }

    private static function generateStubDeep($pattern){
        $lenght = strlen($pattern);
        $pattern_encapsulation_deep = [];
        $arr_stubdeep = [];
        $current_count_opening_bracket = 0;
        $bracket_open = false;

        for($i=0;$i<$lenght;$i++){
            $symbol = $pattern[$i];
            $pattern_encapsulation_deep[$i] = $symbol;
            if($bracket_open == true){
                $pattern_encapsulation_deep[$i] = "-";
                $tmp[] = $symbol;
            }else{
                $pattern_encapsulation_deep[$i] = $symbol;
            }
            if($symbol == '('){
                $current_count_opening_bracket++;
                if($current_count_opening_bracket == 1){
                    $pattern_encapsulation_deep[$i] = "[";
                    $bracket_open = true;
                    $tmp = [];
                    $tmp[] = "(";
                }
            }
            if($symbol == ')'){
                $current_count_opening_bracket--;
                if($current_count_opening_bracket == 0){
                    $pattern_encapsulation_deep[$i] = "]";
                    $bracket_open = false;
                    $arr_stubdeep[] = $tmp;

                }
            }
        }

        $pattern_encapsulation_deep = implode("", $pattern_encapsulation_deep);
        foreach($arr_stubdeep as &$stubdeep){
            $stubdeep = implode("", $stubdeep);
        }

        $pattern_encapsulation_deep_mod = preg_replace("/[[-]+]/", "[-]", $pattern_encapsulation_deep);
        $arr = preg_split("/[\[\]]/", $pattern_encapsulation_deep_mod);

        $pattern_numered_stubdeep = "";
        $counter=0;
        foreach($arr as $val){
            if($val == "-") {
                $pattern_numered_stubdeep .= "[" . $counter++ . "]";
            }else{
                $pattern_numered_stubdeep .= $val;
            }
        }
        $response = [];
        $response["pattern_numered_stubdeep"] = $pattern_numered_stubdeep;
        $response["pattern_arr_stubdeep"] = $arr_stubdeep;

        return $response;
    }
}