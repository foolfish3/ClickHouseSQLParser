<?php
//count(distinct x) => OK
//INTERVAL          => OK
//EXTRACT           => OK
//Conditional Expression => NO
//Conditional Operator(a?b:c) => OK
//Lambda Creation Operator => OK
//Array Creation Operator => OK
//Parametric Aggregate Functions => OK
namespace ClickHouseSQLParser;

require_once __DIR__ . "/ClickHouseSQLParserTokenizer.php";

class ClickHouseSQLParser extends ClickHouseSQLParserTokenizer
{
    protected static $precedence_map = array(
        "as" => 1,
        "lambda" => 2,
        "if" => 2,
        "or" => 3,
        "and" => 4,
        "not" => 5,
        "between" => 6,
        "notBetween" => 6,
        "equals" => 7,
        "notEquals" => 7,
        "greater" => 7,
        "greaterOrEquals" => 7,
        "less" => 7,
        "lessOrEquals" => 7,
        "isNull" => 8,
        "isNotNull" => 8,
        "like" => 8,
        "notLike" => 8,
        "in" => 8,
        "notIn" => 8,
        "concat" => 9,
        "plus" => 10,
        "minus" => 10,
        "multiply" => 11,
        "divide" => 11,
        "modulo" => 11,
        "interval" => 12,
        "extract" => 12,
        "negate" => 13,
    );

    protected static $operator_to_function_map = array(
        "=" => "equals", "!=" => "notEquals", "<>" => "notEquals", ">" => "greater", ">=" => "greaterOrEquals", "<" => "less", "<=" => "lessOrEquals",
        "+" => "plus", "-" => "minus",
        "*" => "multiply", "%" => "modulo", "/" => "divide", "||" => "concat",
        "OR" => "or", "AND" => "and", "LIKE" => "like",
    );

    protected static $function_to_operator_map = array(
        "equals" => "=",
        "notEquals" => "!=",
        "greater" => ">",
        "greaterOrEquals" => ">=",
        "less" => "<",
        "lessOrEquals" => "<=",
        "plus" => "+",
        "minus" => "-",
        "multiply" => "*",
        "divide" => "/",
        "modulo" => "%",
        "and" => " and ",
        "or" => " or ",
        "like" => " like ",
        "notLike" => " not like ",
        "in" => " in ",
        "notIn" => " not in ",
        "lambda" => "->",
    );

    protected static $keywords_map = array(
        "WITH" => 1, "SELECT" => 1, "FROM" => 1, "FINAL" => 1, "HAVING" => 1,
        "WHERE" => 1, "PREWHERE" => 1, "ORDER" => 1, "LIMIT" => 1, "CROSS" => 1,
        "INNER" => 1, "LEFT" => 1, "RIGHT" => 1, "FULL" => 1, "JOIN" => 1,
        "ON" => 1, "USING" => 1, "ARRAY" => 1, "ALL" => 1, "ANY" => 1, "ASOF" => 1,
    );

    protected static $interval_map = array(
        "SECOND" => "toIntervalSecond",
        "MINUTE" => "toIntervalMinute",
        "HOUR" => "toIntervalHour",
        "DAY" => "toIntervalDay",
        "WEEK" => "toIntervalWeek",
        "MONTH" => "toIntervalMonth",
        "QUARTER" => "toIntervalQuarter",
        "YEAR" => "toIntervalYear",
    );

    protected static $interval_map_reverse = array(
        "toIntervalSecond" => "SECOND",
        "toIntervalMinute" => "MINUTE",
        "toIntervalHour" => "HOUR",
        "toIntervalDay" => "DAY",
        "toIntervalWeek" => "WEEK",
        "toIntervalMonth" => "MONTH",
        "toIntervalQuarter" => "QUARTER",
        "toIntervalYear" => "YEAR",
    );

    protected static $extract_map = array(
        "SECOND" => "toSecond",
        "MINUTE" => "toMinute",
        "HOUR" => "toHour",
        "DAY" => "toDayOfMonth",
        "MONTH" => "toMonth",
        "YEAR" => "toYear",
    );

    protected static $extract_map_reverse = array(
        "toSecond" => "SECOND",
        "toMinute" => "MINUTE",
        "toHour" => "HOUR",
        "toDayOfMonth" => "DAY",
        "toMonth" => "MONTH",
        "toYear" => "YEAR",
    );

    public static function replace_expr($expr, $replace_expr)
    {
        foreach (["join", "on", "using", "alias", "order"] as $key) {
            unset($replace_expr[$key]);
            if (isset($expr[$key])) {
                $replace_expr[$key] = $expr[$key];
            }
        }
        return $replace_expr;
    }


    public static function compact_expr($expr, $compact_and = 1, $compact_or = 1, $compact_concat = 1)
    {
        self::internal_compact_expr($expr, $compact_and, $compact_or, $compact_concat);
        return $expr;
    }

    protected static function internal_compact_expr(&$expr, $and, $or, $concat)
    {
        switch ($expr["type"]) {
            case self::T_FUNCTION:
                foreach ($expr["sub_tree"] as &$sub_expr) {
                    self::internal_compact_expr($sub_expr, $and, $or, $concat);
                }
                unset($sub_expr);
                switch ($expr["expr"]) {
                    case "and":
                        if (!$and) {
                            break;
                        }
                        $sub_tree = array();
                        foreach ($expr["sub_tree"] as $sub_expr) {
                            if (self::is_expr_of_function($sub_expr, "and")) {
                                foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                                    $sub_tree[] = $sub_sub_expr;
                                }
                            } else {
                                $sub_tree[] = $sub_expr;
                            }
                        }
                        $sub_tree2 = array();
                        foreach ($sub_tree as $sub_expr) {
                            if (!self::is_expr_const_true($sub_expr)) {
                                $sub_tree2[] = $sub_expr;
                            }
                        }
                        if (\count($sub_tree2) == 0) {
                            $expr = self::replace_expr($expr, self::$EXP_CONSTANT_1);
                        } elseif (\count($sub_tree2) == 1) {
                            $expr = self::replace_expr($expr, $sub_tree2[0]);
                        } else {
                            $expr["sub_tree"] = $sub_tree2;
                        }
                        break;
                    case "or":
                        if (!$or) {
                            break;
                        }
                        $sub_tree = array();
                        foreach ($expr["sub_tree"] as $sub_expr) {
                            if (self::is_expr_of_function($sub_expr, "or")) {
                                foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                                    $sub_tree[] = $sub_sub_expr;
                                }
                            } else {
                                $sub_tree[] = $sub_expr;
                            }
                        }
                        $sub_tree2 = array();
                        foreach ($sub_tree as $sub_expr) {
                            if (!self::is_expr_const_false($sub_expr)) {
                                $sub_tree2[] = $sub_expr;
                            }
                        }
                        if (\count($sub_tree2) == 0) {
                            $expr = self::replace_expr($expr, self::$EXP_CONSTANT_0);
                        } elseif (\count($sub_tree2) == 1) {
                            $expr = self::replace_expr($expr, $sub_tree2[0]);
                        } else {
                            $expr["sub_tree"] = $sub_tree2;
                        }
                        break;
                    case "concat":
                        if (!$concat) {
                            break;
                        }
                        $sub_tree = array();
                        foreach ($expr["sub_tree"] as $sub_expr) {
                            if (self::is_expr_of_function($sub_expr, "concat")) {
                                foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                                    $sub_tree[] = $sub_sub_expr;
                                }
                            } else {
                                $sub_tree[] = $sub_expr;
                            }
                        }
                        $sub_tree2 = array();
                        foreach ($sub_tree as $sub_expr) {
                            if (!self::is_expr_const_empty_string($sub_expr)) {
                                $sub_tree2[] = $sub_expr;
                            }
                        }
                        if (\count($sub_tree2) == 0) {
                            $expr = self::replace_expr($expr, self::$EXP_CONSTANT_EMPTY_STRING);
                        } elseif (\count($sub_tree2) == 1) {
                            $expr = self::replace_expr($expr, $sub_tree2[0]);
                        } else {
                            $expr["sub_tree"] = $sub_tree2;
                        }
                        break;
                }
                break;
            case self::T_SQL_UNION_ALL:
                foreach ($expr["sub_tree"] as &$sub_expr) {
                    self::internal_compact_expr($sub_expr, $and, $or, $concat);
                }
                break;
            case self::T_SUBQUERY:
                self::internal_compact_expr($expr["sub_tree"], $and, $or, $concat);
                break;
            case self::T_SQL_SELECT:
                foreach (["WITH", "SELECT", "FROM", "ARRAYJOIN", "GROUPBY", "ORDERBY"] as $key) {
                    if (isset($expr[$key])) {
                        foreach ($expr[$key] as &$sub_expr) {
                            self::internal_compact_expr($sub_expr, $and, $or, $concat);
                        }
                    }
                }
                foreach (["PREWHERE", "WHERE", "HAVING"] as $key) {
                    if (isset($expr[$key])) {
                        self::internal_compact_expr($expr[$key], $and, $or, $concat);
                    }
                }
                break;
        }
        foreach (["using"] as $key) {
            if (isset($expr[$key])) {
                foreach ($expr[$key] as &$sub_expr) {
                    self::internal_compact_expr($sub_expr, $and, $or, $concat);
                }
            }
        }
        foreach (["on"] as $key) {
            if (isset($expr[$key])) {
                self::internal_compact_expr($expr[$key], $and, $or, $concat);
            }
        }
    }


    public static function parse($sql)
    {
        if (preg_match("{^[\s(]*(?:WITH|SELECT)\s}si", $sql)) {
            $tokens = self::token_get_all($sql, true);
            list($subquery, $index) = self::get_next_expr($tokens, 0, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            if (!self::is_expr_of($subquery, self::T_SUBQUERY)) {
                throw new \ErrorException("cannot parse as sql, not a subquery, maybe BUG");
            }
            return $subquery["sub_tree"];
        } else {
            return self::SQL_ANY($sql);
        }
    }

    public static function parse_expr($str)
    {
        $tokens = self::token_get_all($str, true);
        list($expr, $index) = self::get_next_expr($tokens, 0, 0);
        if ($index != \count($tokens)) {
            throw new \ErrorException("cannot parse as expr, some token left");
        }
        return $expr;
    }

    protected static function hasAlias($p)
    {
        return isset($p["alias"]) && $p["alias"] !== false && $p["alias"] !== '';
    }

    protected static function aliasStr($p)
    {
        if (!self::hasAlias($p)) {
            return "";
        } else {
            return " AS " . self::backquote($p["alias"]);
        }
    }

    protected static function orderStr($p)
    {
        if (!isset($p["order"]) || $p["order"] === false || $p["order"] === '') {
            return "";
        } else {
            return " " . $p["order"];
        }
    }

    protected static function joinStr($p)
    {
        if (!isset($p["join"]) || $p["join"] === false) {
            return "";
        } else {
            return " {$p["join"]} JOIN ";
        }
    }

    protected static function usingStr($p)
    {
        if (!isset($p["using"]) || $p["using"] === false) {
            return "";
        } else {
            $s = "";
            foreach ($p["using"] as $k => $sub) {
                if ($k !== 0) {
                    $s .= ",";
                }
                $s .= self::create($sub);
            }
            return " USING ($s)";
        }
    }

    protected static function onStr($p)
    {
        if (!isset($p["on"]) || $p["on"] === false || $p["on"] === '') {
            return "";
        } else {
            return " ON (" . self::create($p["on"]) . ")";
        }
    }

    private static function create2($p)
    {
        switch ($p["type"]) {
            case self::T_CONSTANT_NULL:
                return array("NULL" . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_IDENTIFIER_ASTERISK:
                return array("*" . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_CONSTANT_LNUMBER:
            case self::T_CONSTANT_DNUMBER:
                return array($p["expr"] . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_EXP_ANY:
                return array("(" . $p["expr"] . ")" . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_CONSTANT_STRING:
                return array(self::mysql_encode_str($p["expr"]) . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_IDENTIFIER_COLREF:
            case self::T_IDENTIFIER_TABLE:
                $ss = array();
                foreach ($p["parts"] as $part) {
                    $ss[] = self::backquote($part, 0, 1);
                }
                return array(self::joinStr($p) . \implode(".", $ss) . self::aliasStr($p) . self::usingStr($p) . self::onStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_PARAMETRIC_FUNCTION:
                $s = $p["expr"];
                foreach ($p["sub_tree"] as $k => $sub_tree) {
                    $s.= self::create2(self::EXP_FUNCTION("",$sub_tree))[0];
                }
                return array($s . self::aliasStr($p) . self::orderStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_FUNCTION:
                $func = $p["expr"];
                $func_upper = \strtoupper($p["expr"]);
                $sub_tree=@$p["sub_tree"] ? $p["sub_tree"] : array();
                $s = "";
                $map=array("COUNT"=>"count");
                if(isset($map[$func_upper])){
                    $func=$map[$func_upper];
                }
                switch ($func) {
                    case "count":
                        if(\count($sub_tree)===0){
                            $precedence = 100;
                            $s = "count(*)";
                            break;
                        }
                        goto F1;
                    case "countDistinct":
                        $precedence = 100;
                        foreach ($sub_tree as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            list($str) = self::create2($sub);
                            $s .= $str;
                        }
                        $s = "count(distinct $s)";
                        break;
                    case "toIntervalSecond":
                    case "toIntervalMinute":
                    case "toIntervalHour":
                    case "toIntervalDay":
                    case "toIntervalWeek":
                    case "toIntervalMonth":
                    case "toIntervalQuarter":
                    case "toIntervalYear":
                        $precedence = self::$precedence_map["interval"];
                        list($str, $sub_precedence) = self::create2($sub_tree[0]);
                        if ($sub_precedence <= $precedence) {
                            $str = "($str)";
                        }
                        $s .= "INTERVAL $str " . self::$interval_map_reverse[$func];
                        break;
                    case "toSecond":
                    case "toMinute":
                    case "toHour":
                    case "toDayOfMonth":
                    case "toMonth":
                    case "toYear":
                        $precedence = self::$precedence_map["extract"];
                        list($str, $sub_precedence) = self::create2($sub_tree[0]);
                        if ($sub_precedence <= $precedence) {
                            $str = "($str)";
                        }
                        $s .= "EXTRACT(" . self::$extract_map_reverse[$func] . " FROM $str)";
                        break;
                    case "equals":
                    case "notEquals":
                    case "greater":
                    case "greaterOrEquals":
                    case "less":
                    case "lessOrEquals":
                    case "like":
                    case "notLike":
                    case "plus":
                    case "minus":
                    case "multiply":
                    case "divide":
                    case "modulo":
                        $precedence = self::$precedence_map[$func];
                        list($str, $sub_precedence) = self::create2($sub_tree[0]);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        list($str, $sub_precedence) = self::create2($sub_tree[1]);
                        if ($sub_precedence <= $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        break;
                    case "in":
                    case "notIn":
                        $precedence = self::$precedence_map[$func];
                        $val = $sub_tree[0];
                        if (self::is_expr_of_function($val, "tuple")) {
                            $val["expr"] = "";
                        }
                        list($str, $sub_precedence) = self::create2($val);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        $val = $sub_tree[1];
                        if (!self::is_expr_of_function($val, "tuple")) {
                            throw new \ErrorException("BUG");
                        }
                        $s .= "(";
                        foreach ($val["sub_tree"] as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            if (self::is_expr_of_function($val, "tuple")) {
                                $sub["expr"] = "";
                            }
                            list($str) = self::create2($sub);
                            $s .= $str;
                        }
                        $s .= ")";
                        break;
                    case "lambda":
                        $precedence = self::$precedence_map[$func];
                        $val = $sub_tree[0];
                        if (self::is_expr_of_function($val, "tuple")) {
                            $val["expr"] = "";
                            if (\count($val["sub_tree"]) === 1) {
                                $val = $val["sub_tree"][0];
                            }
                        }
                        list($str, $sub_precedence) = self::create2($val);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        $s .= self::create2($sub_tree[1])[0];
                        break;
                    case "or":
                    case "and":
                        $precedence = self::$precedence_map[$func];
                        $s = "";
                        foreach ($p["sub_tree"] as $k => $sub) {
                            if ($k !== 0) {
                                $s .= self::$function_to_operator_map[$func];
                            }
                            list($str, $sub_precedence) = self::create2($sub);
                            if ($sub_precedence < $precedence) {
                                $s .= "($str)";
                            } else {
                                $s .= "$str";
                            }
                        }
                        break;
                    case "negate":
                        $precedence = 100;
                        list($str, $sub_precedence) = self::create2($sub_tree[0]);
                        if ($sub_precedence < $precedence) {
                            $s .= "(-($str))";
                        } else {
                            $s .= "(-$str)";
                        }
                        break;
                    case "array":
                        $precedence = 100;
                        foreach ($sub_tree as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            list($str) = self::create2($sub);
                            $s .= $str;
                        }
                        $s = "[$s]";
                        break;
                    default:
F1:                     $precedence = 100;
                        foreach ($sub_tree as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            list($str) = self::create2($sub);
                            $s .= $str;
                        }
                        $s = $p["expr"] . "($s)";
                }
                if (self::hasAlias($p)) {
                    if ($precedence < self::$precedence_map["negate"]) {
                        $s = "($s)";
                    }
                    $precedence = 0;
                }
                return array($s . self::aliasStr($p) . self::orderStr($p), $precedence);
            case self::T_SUBEXP:
            case self::T_SUBQUERY:
                return array(self::joinStr($p) . "(" . self::create2($p["sub_tree"])[0] . ")" . self::aliasStr($p) . self::orderStr($p) . self::usingStr($p) . self::onStr($p), (self::hasAlias($p) ? 0 : 100));
            case self::T_SQL_ANY:
                return array($p["expr"], 0);
            case self::T_SQL_UNION_ALL:
                $s = "";
                foreach ($p["sub_tree"] as $k => $query) {
                    if ($k != 0) {
                        $s .= " UNION ALL ";
                    }
                    $s .= "(" . self::create2($query)[0] . ")";
                }
                return array($s, 100);
            case self::T_SQL_SELECT:
                $s = "";
                if (@$p["WITH"]) {
                    $s .= "WITH ";
                    foreach ($p["WITH"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .= self::create2($expr)[0];
                    }
                    $s .= " ";
                }
                $s .= "SELECT";
                if (@$p["SELECT_OPTIONS"]) {
                    foreach ($p["SELECT_OPTIONS"] as $option) {
                        $s .= " $option";
                    }
                }
                $s .= " ";
                foreach ($p["SELECT"] as $k => $expr) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .= self::create2($expr)[0];
                }
                if (@$p["FROM"]) {
                    $s .= " FROM ";
                    foreach ($p["FROM"] as $k => $expr) {
                        $s .= self::create2($expr)[0];
                        if ($k == 0 && @$p["ARRAYJOIN"]) {
                            $s .= " ARRAY JOIN ";
                            foreach ($p["ARRAYJOIN"] as $k => $expr2) {
                                if ($k != 0) {
                                    $s .= ",";
                                }
                                $s .= self::create2($expr2)[0];
                            }
                            $s .= " ";
                        }
                    }
                }
                if (@$p["PREWHERE"]) {
                    $s .= " PREWHERE " . self::create2($p["PREWHERE"])[0];
                }
                if (@$p["WHERE"]) {
                    $s .= " WHERE " . self::create2($p["WHERE"])[0];
                }
                if (@$p["GROUPBY"]) {
                    $s .= " GROUP BY ";
                    foreach ($p["GROUPBY"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .= self::create2($expr)[0];
                    }
                }
                if (@$p["HAVING"]) {
                    $s .= " HAVING " . self::create2($p["HAVING"])[0];
                }
                if (@$p["ORDERBY"]) {
                    $s .= " ORDER BY ";
                    foreach ($p["ORDERBY"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .= self::create2($expr)[0];
                    }
                }
                if (@$p["LIMITBY"]) {
                    $s .= " LIMIT ";
                    if (isset($p["LIMITBY"]["offset"])) {
                        $s .= $p["LIMITBY"]["offset"] . ",";
                    }
                    $s .= $p["LIMITBY"]["row_count"] . " BY ";
                    foreach ($p["LIMITBY"]["expr_list"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .= self::create2($expr)[0];
                    }
                }
                if (@$p["LIMIT"]) {
                    $s .= " LIMIT ";
                    if (isset($p["LIMIT"]["offset"])) {
                        $s .= $p["LIMIT"]["offset"] . ",";
                    }
                    $s .= $p["LIMIT"]["row_count"];
                }

                if (@$p["SETTINGS"]) {
                    $s .= " SETTINGS ";
                    $k = 0;
                    foreach ($p["SETTINGS"] as $key => $expr) {
                        if ($k++ != 0) {
                            $s .= ",";
                        }
                        $s .= "$key=" . self::create2($expr)[0];
                    }
                }

                return array($s, 0);
            default:
                throw new \ErrorException("BUG");
        }
    }

    public static function create($p, $use_function_instead_operator = false)
    {
        return self::create2($p, $use_function_instead_operator)[0];
    }


    protected static function get_next_expr($tokens, $index, $precedence)
    {
        $token = @$tokens[$index];
        if (is_string($token)) {
            switch ($token) {
                case "+":
                    if (self::$precedence_map["negate"] <= $precedence) {
                        throw new \ErrorException("unexpect token " . self::token_to_string($token));
                    }
                    list($val1, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map["negate"]);
                    break;
                case "-":
                    list($val1, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map["negate"]);
                    if (self::is_expr_of($val1, self::T_CONSTANT_NUMBER) && $val1["expr"][0] !== "-") {
                        if ($val1["expr"] !== "0") {
                            $val1["expr"] =   "-" . $val1["expr"];
                        }
                    } else {
                        $val1 = self::EXP_FUNCTION("negate", array($val1));
                    }
                    break;
                case "*":
                    $val1 = self::$EXP_IDENTIFIER_ASTERISK;
                    $index++;
                    break;
                case "(":
                    $val1 = self::EXP_IDENTIFIER_COLREF([""]);
                    break;
                case "[":
                    $token = @$tokens[++$index];
                    $sub_tree = array();
                    if ($token !== "]") {
                        for (;;) {
                            list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                            $sub_tree[] = $expr;
                            $token = @$tokens[$index];
                            if ($token === "]") {
                                $index++;
                                break;
                            } elseif ($token === ",") {
                                $index++;
                            } else {
                                throw new \ErrorException("expect ) or , got " . self::token_to_string($token));
                            }
                        }
                    } else {
                        $index++;
                    }
                    $val1 = self::EXP_FUNCTION("array", $sub_tree);
                    break;
                default:
                    throw new \ErrorException("unexpect token " . self::token_to_string($token));
            }
        } else {
            switch ($token[0]) {
                case self::T_CONSTANT_NULL:
                case self::T_CONSTANT_LNUMBER:
                case self::T_CONSTANT_DNUMBER:
                case self::T_CONSTANT_STRING:
                    $val1 = self::token_to_constant_expr($token);
                    $index++;
                    break;
                default:
                    switch (\strtoupper($token[1])) {
                        case "NOT":
                            list($val1, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map["not"]);
                            $val1 = self::EXP_FUNCTION("not", array($val1));
                            break;
                        case "INTERVAL":
                            list($val1, $index) = self::get_next_expr($tokens, $index + 1, 0);
                            $token = @$tokens[$index];
                            if (!(\is_array($token) && isset(self::$interval_map[\strtoupper($token[1])]))) {
                                throw new \ErrorException("expect SECOND, MINUTE, HOUR, DAY, WEEK, MONTH, QUARTER, YEAR got " . self::token_to_string($token));
                            }
                            $val1 = self::EXP_FUNCTION(self::$interval_map[\strtoupper($token[1])], array($val1));
                            $index++;
                            break;
                        case "WITH":
                        case "SELECT":
                            list($val1, $index) = self::get_next_select($tokens, $index);
                            $val1 = self::EXP_SUBQUERY($val1);
                            break;
                        default:
                            $val1 = self::EXP_IDENTIFIER_COLREF($token[1]);
                            $index++;
                    }
            }
        }
        for (;;) {
            $token = @$tokens[$index];
            if ($token === NULL) {
                return array($val1, $index);
            }
            $operator  = \is_string($token) ? $token : \strtoupper($token[1]);
            $old_index = $index;
            switch ($operator) {
                case ")":
                case ",":
                case "]":
                case "->":
                case ":":
                    return array($val1, $index);
                case "?":
                    $operator = "if";
                    if (self::$precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                    $token = @$tokens[$index];
                    if (!self::is_token_of($token, ":")) {
                        throw new \ErrorException("expect ':' after '?' got " . self::token_to_string($token));
                    }
                    list($val3, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                    $val1 = self::EXP_FUNCTION("if", array($val1, $val2, $val3));
                    continue 2;
                case ".":
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                        $val1 = self::EXP_FUNCTION("tupleElement", array($val1, self::EXP_CONSTANT_LNUMBER($token[1])));
                        $index++;
                        continue 2;
                    } elseif (self::is_token_of($token, self::T_IDENTIFIER)) {
                        if (!self::is_expr_of($val1, self::T_IDENTIFIER_COLREF)) {
                            throw new \ErrorException("BUG");
                        }
                        $val1["parts"][] = self::parse_colref($token[1])[0];
                        $index++;
                        continue 2;
                    } else {
                        throw new \ErrorException("expect IDENTIFIER got " . self::token_to_string($token));
                    }
                case "[":
                    list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
                    $val1 = self::EXP_FUNCTION("arrayElement", array($val1, $expr));
                    $token = @$tokens[$index];
                    if ($token !== "]") {
                        throw new \ErrorException("expect ] got " . self::token_to_string($token));
                    }
                    $index++;
                    continue 2;
                case "(":
                    $token = @$tokens[++$index];
                    $sub_tree = array();
                    $to_upper_func_name = "";
                    if (self::is_expr_of($val1, self::T_IDENTIFIER_COLREF)) {
                        if (\count($val1["parts"]) != 1) {
                            throw new \ErrorException("expect FUNCTION NAME got " . self::create($val1));
                        }
                        $to_upper_func_name = \strtoupper($val1["parts"][0]);
                    } elseif (self::is_expr_of($val1, self::T_FUNCTION)) {
                        $val1["type"] = self::T_PARAMETRIC_FUNCTION;
                        $val1["sub_tree"] = array($val1["sub_tree"]);
                    } elseif (self::is_expr_of($val1, self::T_PARAMETRIC_FUNCTION)) {
                    } else {
                        throw new \ErrorException("expect FUNCTION NAME got " . self::create($val1));
                    }
                    //AVG | MAX | MIN | SUM | COUNT
                    //length | CHAR_LENGTH | CHARACTER_LENGTH
                    //IF concat

                    if ($token !== ")") {
                        switch ($to_upper_func_name) {
                            case "COUNT":
                                if (self::is_token_of($token, "DISTINCT")) {
                                    $token = @$tokens[++$index];
                                    $val1["parts"][0] = "countDistinct";
                                }elseif($token==="*"){
                                    $token = @$tokens[++$index];
                                    if ($token !== ")") {
                                        throw new \ErrorException("expect ')' after 'count(*' got " . self::token_to_string($token));
                                    }
                                    goto F4;
                                }
                                break;
                            case "EXTRACT":
                                $func = @self::$extract_map[\strtoupper(self::token_to_string($token))];
                                if ($func === NULL) {
                                    throw new \ErrorException("expect 'SECOND' 'MINUTE' 'HOUR' 'DAY' 'MONTH' 'YEAR' after 'EXTRACT' got " . self::token_to_string($token));
                                }
                                $token = @$tokens[++$index];
                                if (!self::is_token_of($token, "FROM")) {
                                    throw new \ErrorException("expect 'FROM' after 'EXTRACT ... ' got " . self::token_to_string($token));
                                }
                                $token = @$tokens[++$index];
                                $val1["parts"][0] = $func;
                                break;
                        }
                        for (;;) {
                            list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                            $token = @$tokens[$index];
                            if ($token === "->") {
                                if (!self::is_expr_of($expr, self::T_FUNCTION)) {
                                    $sub_tree[] = $expr;
                                    $expr = self::EXP_FUNCTION('tuple', $sub_tree);
                                    $sub_tree = array();
                                }
                                if ($expr["expr"] !== "tuple") {
                                    throw new \ErrorException("expect IDENTIFIER before -> ");
                                }
                                foreach ($expr["sub_tree"] as $e) {
                                    if (!self::is_expr_of($e, self::T_IDENTIFIER_COLREF) || \count($e["parts"]) != 1) {
                                        throw new \ErrorException("expect PARAM NAME got " . self::create($e));
                                    }
                                }
                                list($expr2, $index) = self::get_next_expr($tokens, $index + 1, 0);
                                $expr = self::EXP_FUNCTION("lambda", array($expr, $expr2));
                                $token = @$tokens[$index];
                            }
                            if ($token === ")") {
                                $sub_tree[] = $expr;
                                $index++;
                                break;
                            } elseif ($token === ",") {
                                $sub_tree[] = $expr;
                                $index++;
                            } else {
                                throw new \ErrorException("expect ) or , got " . self::token_to_string($token));
                            }
                        }
                    } else {
F4:                     $index++;
                    }
                    if (self::is_expr_of($val1, self::T_IDENTIFIER_COLREF)) {
                        if ($val1["parts"][0] === "") {
                            if (\count($sub_tree) == 0) {
                                throw new \ErrorException("unexpect token ()");
                            } elseif (\count($sub_tree) == 1) {
                                $val1 = $sub_tree[0];
                            } else {
                                $val1 = self::EXP_FUNCTION('tuple', $sub_tree);
                            }
                        } else {
                            $val1 = self::EXP_FUNCTION($val1["parts"][0], $sub_tree);
                        }
                    } elseif (self::is_expr_of($val1, self::T_PARAMETRIC_FUNCTION)) {
                        $val1["sub_tree"][] = $sub_tree;
                    } else {
                        throw new \ErrorException("BUG");
                    }
                    continue 2;
                case "NOT":
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "IN")) {
                        $operator = "notIn";
                        goto F3;
                    } elseif (self::is_token_of($token, "LIKE")) {
                        $operator = "notLike";
                        goto F2;
                    } elseif (self::is_token_of($token, "BETWEEN")) { //NOT BETWEEN
                        $operator = "notBetween";
                        if (self::$precedence_map[$operator] <= $precedence) {
                            return array($val1, $old_index);
                        }
                        list($val2, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                        $token = @$tokens[$index];
                        if (!self::is_token_of($token, "AND")) {
                            throw new \ErrorException("expect 'AND' after 'BETWEEN ... ' got " . self::token_to_string($token));
                        }
                        list($val3, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                        $val1 = self::EXP_FUNCTION("or", array(
                            self::EXP_FUNCTION("less", array($val1, $val2)),
                            self::EXP_FUNCTION("greater", array($val1, $val3))
                        ));
                        continue 2;
                    } else {
                        throw new \ErrorException("expect 'IN' 'LIKE' 'BETWEEN' after 'NOT' got " . self::token_to_string($token));
                    }
                case "AND":
                case "OR":
                case "LIKE":
                case "=":
                case "!=":
                case "<>":
                case ">":
                case ">=":
                case "<":
                case "<=":
                case "+":
                case "-":
                case "*":
                case "/":
                case "||":
                    $operator = self::$operator_to_function_map[$operator];
                    F2: if (self::$precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    $token = @$tokens[++$index];
                    if ($token === NULL) {
                        throw new \ErrorException("expect (EXPR) got " . self::token_to_string($token));
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index, self::$precedence_map[$operator]);
                    $val1 = self::EXP_FUNCTION($operator, array($val1, $val2));
                    continue 2;
                case "IS":
                    $token = @$tokens[++$index];
                    $not = false;
                    if (self::is_token_of($token, "NOT")) {
                        $token = @$tokens[++$index];
                        $not = true;
                    }
                    if (self::is_token_of($token, "NULL")) {
                        $index++;
                        $operator = $not ? "isNotNull" : "isNull";
                        if (self::$precedence_map[$operator] <= $precedence) {
                            return array($val1, $old_index);
                        }
                        $val1 = self::EXP_FUNCTION($operator, array($val1));
                        continue 2;
                    } else {
                        throw new \ErrorException("expect 'NULL' or 'NOT NULL' after 'IS' got " . self::token_to_string($token));
                    }
                case "IN":
                    $operator = "in";
                    F3: if (self::$precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    $token = @$tokens[++$index]; //(
                    if (!self::is_token_of($token, "(")) {
                        throw new \ErrorException("expect '(' got " . self::token_to_string($token));
                    }
                    $token = @$tokens[++$index];
                    if ($token === ")") {
                        throw new \ErrorException("in-list cannot be empty");
                    }
                    $sub_tree = array();
                    for (;;) {
                        list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                        $sub_tree[] = $expr;
                        $token = @$tokens[$index];
                        if ($token === ")") {
                            $index++;
                            break;
                        } elseif ($token === ",") {
                            $index++;
                        } else {
                            throw new \ErrorException("expect ) or , got " . self::token_to_string($token));
                        }
                    }
                    $val1 = self::EXP_FUNCTION($operator, array($val1, self::EXP_FUNCTION("tuple", $sub_tree)));
                    continue 2;
                case "BETWEEN":
                    $operator = "between";
                    if (self::$precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                    $token = @$tokens[$index];
                    if (!self::is_token_of($token, "AND")) {
                        throw new \ErrorException("expect 'AND' after 'BETWEEN ... ' got " . self::token_to_string($token));
                    }
                    list($val3, $index) = self::get_next_expr($tokens, $index + 1, self::$precedence_map[$operator]);
                    $val1 = self::EXP_FUNCTION("and", array(
                        self::EXP_FUNCTION("greaterOrEquals", array($val1, $val2)),
                        self::EXP_FUNCTION("lessOrEquals", array($val1, $val3))
                    ));
                    continue 2;
                case "AS":
                    $operator = "as";
                    if (self::$precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    if (self::is_expr_of($val1, self::T_IDENTIFIER_ASTERISK)) {
                        throw new \ErrorException("asterisk cannot has alias");
                    }
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, self::T_IDENTIFIER)) {
                        throw new \ErrorException("expect IDENTIFIER got " . self::token_to_string($token));
                    }
                    if (isset($val1["alias"])) {
                        $val1 = self::EXP_SUBEXP($val1);
                    }
                    $val1["alias"] = self::parse_colref($token[1])[0];
                    $index++;
                    continue 2;
                case "UNION":
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "ALL")) {
                        throw new \ErrorException("expect 'ALL' after 'UNION' got " . self::token_to_string($token));
                    }
                    if (!self::is_expr_of($val1, self::T_SUBQUERY)) {
                        throw new \ErrorException("expect (QUERY) before 'UNION ALL' ");
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, 0);
                    if (!self::is_expr_of($val2, self::T_SUBQUERY)) {
                        throw new \ErrorException("expect (QUERY) after 'UNION ALL' ");
                    }
                    $query = $val1["sub_tree"];
                    if ($query["type"] == self::T_SQL_SELECT) {
                        $query = self::SQL_UNION_ALL([$query]);
                    }
                    $query2 = $val2["sub_tree"];
                    if ($query2["type"] == self::T_SQL_SELECT) {
                        $query2 = self::SQL_UNION_ALL([$query2]);
                    }
                    foreach ($query2["sub_tree"] as $q) {
                        $query["sub_tree"][] = $q;
                    }
                    $val1["sub_tree"] = $query;
                    continue 2;
                default:
                    if (\is_string($token)) {
                        throw new \ErrorException("unexpect token " . self::token_to_string($token));
                    }
                    return array($val1, $index);
            }
        }
        throw new \ErrorException("BUG");
    }



    protected static function is_token_keyword($token)
    {
        return \is_array($token) && isset(self::$keywords_map[\strtoupper($token[1])]);
    }

    //[FINAL] [GLOBAL] [ANY|ALL] [SAMPLE] [WITH TOTALS] [INTO OUTFILE filename] [FORMAT format] [Conditional Operator] 不支持
    protected static function get_next_select($tokens, $index)
    {

        $obj = array();
        $obj["type"] = self::T_SQL_SELECT;
        $obj["SELECT"] = array();
        $token = @$tokens[$index];
        if (self::is_token_of($token, "WITH")) {
            $obj["WITH"] = array();
            $index++;
            for (;;) {
                list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                $obj["WITH"][] = $expr;
                $token = @$tokens[$index];
                if ($token === ",") {
                    $index++;
                } else {
                    break;
                }
            }
        }
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "SELECT")) {
            throw new \ErrorException("expect 'SELECT' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (self::is_token_of($token, "DISTINCT")) {
            $obj["SELECT_OPTIONS"] = array();
            $obj["SELECT_OPTIONS"][] = "DISTINCT";
            $token = @$tokens[++$index];
        }
        for (;;) {
            list($expr, $index) = self::get_next_expr($tokens, $index, 0);
            $token = @$tokens[$index];
            if (self::is_token_of($token, self::T_IDENTIFIER) && !self::is_token_keyword($token)) {
                if (self::is_expr_of($expr, self::T_IDENTIFIER_ASTERISK)) {
                    throw new \ErrorException("asterisk cannot has alias");
                }
                if (isset($expr["alias"])) {
                    $expr = self::EXP_SUBEXP($expr);
                }
                $expr["alias"] = self::parse_colref($token[1])[0];
                $index++;
                $token = @$tokens[$index];
            }
            if ($token === ",") {
                $obj["SELECT"][] = $expr;
                $index++;
            } else {
                $obj["SELECT"][] = $expr;
                break;
            }
        }
        $token = @$tokens[$index]; //FROM
        if (self::is_token_of($token, "FROM")) {
            $obj["FROM"] = array();
            list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
            if (self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                $expr["type"] = self::T_IDENTIFIER_TABLE;
            }
            $token = @$tokens[$index];
            if (!isset($expr["alias"]) && self::is_token_of($token, self::T_IDENTIFIER) && !self::is_token_keyword($token)) {
                $expr["alias"] = self::parse_colref($token[1])[0];
                $index++;
                $token = @$tokens[$index];
            }
            $obj["FROM"][] = $expr;
            $token = @$tokens[$index];
            if (self::is_token_of($token, "ARRAY")) {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, "JOIN")) {
                    throw new \ErrorException("expect 'JOIN' after 'ARRAY' got " . self::token_to_string($token));
                }
                $index++;
                $obj["ARRAYJOIN"] = array();
                for (;;) {
                    list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                    $obj["ARRAYJOIN"][] = $expr;
                    $token = @$tokens[$index];
                    if ($token === ",") {
                        $index++;
                    } else {
                        break;
                    }
                }
            }
            for (;;) {
                if (self::is_token_of($token, "INNER")) {
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after 'INNER' got " . self::token_to_string($token));
                    }
                }
                if (self::is_token_of($token, "JOIN")) {
                    $join_type = "INNER";
                } elseif (self::is_token_of($token, "CROSS")) {
                    $join_type = "CROSS";
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after 'CROSS' got " . self::token_to_string($token));
                    }
                } elseif (self::is_token_of($token, ",")) {
                    $join_type = "CROSS";
                } else {
                    if (self::is_token_of($token, "LEFT")) {
                        $join_type = "LEFT";
                    } elseif (self::is_token_of($token, "RIGHT")) {
                        $join_type = "RIGHT";
                    } elseif (self::is_token_of($token, "FULL")) {
                        $join_type = "FULL";
                    } else {
                        break;
                    }
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "OUTER")) {
                        $token = @$tokens[++$index];
                    }
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after '$join_type' got " . self::token_to_string($token));
                    }
                }
                list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
                $expr["join"] = $join_type;
                if (self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                    $expr["type"] = self::T_IDENTIFIER_TABLE;
                }
                $token = @$tokens[$index];
                if (self::is_token_of($token, self::T_IDENTIFIER) && !self::is_token_keyword($token)) {
                    if (self::is_expr_of($expr, self::T_IDENTIFIER_ASTERISK)) {
                        throw new \ErrorException("asterisk cannot has alias");
                    }
                    if (isset($expr["alias"])) {
                        $expr = self::EXP_SUBEXP($expr);
                    }
                    $expr["alias"] = self::parse_colref($token[1])[0];
                    $index++;
                    $token = @$tokens[$index];
                }
                if ($join_type === "CROSS") {
                    $obj["FROM"][] = $expr;
                    continue;
                }
                if (self::is_token_of($token, "USING")) {
                    $expr["using"] = array();
                    $index++;
                    for (;;) {
                        list($expr2, $index) = self::get_next_expr($tokens, $index, 0);
                        $expr["using"][] = $expr2;
                        $token = @$tokens[$index];
                        if ($token === ",") {
                            $index++;
                        } else {
                            break;
                        }
                    }
                } elseif (self::is_token_of($token, "ON")) {
                    list($expr2, $index) = self::get_next_expr($tokens, $index + 1, 0);
                    $expr["on"] = $expr2;
                } else {
                    throw new \ErrorException("expect 'USING' or 'ON' after '$join_type' got " . self::token_to_string($token));
                }
                $obj["FROM"][] = $expr;
            }
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "PREWHERE")) {
            list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["PREWHERE"] = $expr;
        }
        $token = @$tokens[$index];
        if (self::is_token_of($token, "WHERE")) {
            list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["WHERE"] = $expr;
        }
        $token = @$tokens[$index];
        if (self::is_token_of($token, "GROUP")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "BY")) {
                throw new \ErrorException("expect 'BY' after 'GROUP' got " . self::token_to_string($token));
            }
            $index++;
            $obj["GROUPBY"] = array();
            for (;;) {
                list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                $obj["GROUPBY"][] = $expr;
                $token = @$tokens[$index];
                if ($token === ",") {
                    $index++;
                } else {
                    break;
                }
            }
        }
        $token = @$tokens[$index];
        if (self::is_token_of($token, "HAVING")) {
            list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["HAVING"] = $expr;
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "ORDER")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "BY")) {
                throw new \ErrorException("expect 'BY' after 'ORDER' got " . self::token_to_string($token));
            }
            $index++;
            $obj["ORDERBY"] = array();
            for (;;) {
                list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                $token = @$tokens[$index];
                if (self::is_token_of($token, "ASC")) {
                    $expr["order"] = "ASC";
                    $token = @$tokens[++$index];
                } elseif (self::is_token_of($token, "DESC")) {
                    $expr["order"] = "DESC";
                    $token = @$tokens[++$index];
                }
                $obj["ORDERBY"][] = $expr;
                if ($token === ",") {
                    $index++;
                } else {
                    break;
                }
            }
        }

        $old_index = $index;
        $token = @$tokens[$index];
        if (self::is_token_of($token, "LIMIT")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                throw new \ErrorException("expect (INTEGER) after 'LIMIT' got " . self::token_to_string($token));
            }
            $offset = NULL;
            $row_count = $token[1];
            $token = @$tokens[++$index];
            $expr_list = array();
            if ($token === ",") {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                    throw new \ErrorException("expect (INTEGER) after 'LIMIT' got " . self::token_to_string($token));
                }
                list($offset, $row_count) = array($row_count, $token[1]);
                $token = @$tokens[++$index];
            }
            if (self::is_token_of($token, "BY")) {
                $index++;
                for (;;) {
                    list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                    $expr_list[] = $expr;
                    $token = @$tokens[$index];
                    if ($token === ",") {
                        $index++;
                    } else {
                        break;
                    }
                }
                $obj["LIMITBY"] = array(
                    "offset" => $offset, "row_count" => $row_count, "expr_list" => $expr_list,
                );
            } else {
                $index = $old_index;
            }
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "LIMIT")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                throw new \ErrorException("expect (INTEGER) after 'LIMIT' got " . self::token_to_string($token));
            }
            $offset = NULL;
            $row_count = $token[1];
            $token = @$tokens[++$index];
            if ($token === ",") {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                    throw new \ErrorException("expect (INTEGER) after 'LIMIT' got " . self::token_to_string($token));
                }
                list($offset, $row_count) = array($row_count, $token[1]);
                $token = @$tokens[++$index];
            }
            if (self::is_token_of($token, "BY")) {
                throw new \ErrorException("cannot LIMIT BY twice");
            }
            $obj["LIMIT"] = array(
                "offset" => $offset, "row_count" => $row_count
            );
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "SETTINGS")) {
            $obj["SETTINGS"] = array();
            $index++;
            for (;;) {
                $token = @$tokens[$index];
                if (!self::is_token_of($token, self::T_IDENTIFIER_NOQUOTE)) {
                    throw new \ErrorException("expect (IDENTIFIER) after 'SETTINGS' got " . self::token_to_string($token));
                }
                $key = $token[1];
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, "=")) {
                    throw new \ErrorException("expect '=' after 'SETTINGS' got " . self::token_to_string($token));
                }
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, self::T_CONSTANT)) {
                    throw new \ErrorException("expect (CONSTANT) after 'SETTINGS' got " . self::token_to_string($token));
                }
                $obj["SETTINGS"][$key] = self::token_to_constant_expr($token);
                $token = @$tokens[++$index];
                if ($token === ",") {
                    $index++;
                } else {
                    break;
                }
            }
        }
        return array($obj, $index);
    }
}

$p = ClickHouseSQLParser::parse_expr("select 1 in (select 1)");
ClickHouseSQLParser::dump_expr($p);
echo ClickHouseSQLParser::create($p);
