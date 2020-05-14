<?php

namespace ClickHouseSQLParser;

class ClickHouseSQLParser
{
    const T_WHITESPACE = 1000;
    const T_COMMENT = 1010;
    const T_COMMENT_SINGLE_LINE = 1011;
    const T_COMMENT_MULTI_LINE = 1012;
    const T_CONSTANT = 2100;
    const T_CONSTANT_NULL = 2110;
    const T_CONSTANT_NUMBER = 2120;
    const T_CONSTANT_LNUMBER = 2121;
    const T_CONSTANT_DNUMBER = 2122;
    const T_CONSTANT_ENCAPSED_STRING_SINGLE_QUOTE = 2131;
    const T_STRING = 3000;
    const T_STRING_IDENTIFIER = 3100;
    const T_STRING_IDENTIFIER_TABLE = 3140;
    const T_STRING_IDENTIFIER_COLREF = 3130;
    const T_STRING_IDENTIFIER_NOQUOTE = 3110;
    const T_STRING_IDENTIFIER_BACKQUOTE = 3121;
    const T_STRING_IDENTIFIER_DOUBLE_QUOTE = 3122;
    const T_EXP = 5000;      //任意表达式
    const T_FUNCTION = 6000;
    const T_SUBQUERY = 7000;
    const T_SQL_SELECT    = 10001;
    const T_SQL_UNION_ALL = 10002;
    const T_SQL_ANY       = 19999;

    public static function token_name($code)
    {
        $map = array(
            self::T_WHITESPACE => "T_WHITESPACE",
            self::T_COMMENT => "T_COMMENT",
            self::T_COMMENT_SINGLE_LINE => "T_COMMENT_SINGLE_LINE",
            self::T_COMMENT_MULTI_LINE => "T_COMMENT_MULTI_LINE",
            self::T_CONSTANT => "T_CONSTANT",
            self::T_CONSTANT_NULL => "T_CONSTANT_NULL",
            self::T_CONSTANT_NUMBER => "T_CONSTANT_NUMBER",
            self::T_CONSTANT_LNUMBER => "T_CONSTANT_LNUMBER",
            self::T_CONSTANT_DNUMBER => "T_CONSTANT_DNUMBER",
            self::T_CONSTANT_ENCAPSED_STRING_SINGLE_QUOTE => "T_CONSTANT_ENCAPSED_STRING_SINGLE_QUOTE",
            self::T_STRING => "T_STRING",
            self::T_STRING_IDENTIFIER => "T_STRING_IDENTIFIER",
            self::T_STRING_IDENTIFIER_TABLE => "T_STRING_IDENTIFIER_TABLE",
            self::T_STRING_IDENTIFIER_COLREF => "T_STRING_IDENTIFIER_COLREF",
            self::T_STRING_IDENTIFIER_NOQUOTE => "T_STRING_IDENTIFIER_NOQUOTE",
            self::T_STRING_IDENTIFIER_DOUBLE_QUOTE => "T_STRING_IDENTIFIER_DOUBLE_QUOTE",
            self::T_STRING_IDENTIFIER_BACKQUOTE => "T_STRING_IDENTIFIER_BACKQUOTE",
            self::T_EXP => "T_EXP",
            self::T_FUNCTION => "T_FUNCTION",
            self::T_SUBQUERY => "T_SUBQUERY",
            self::T_SQL_SELECT => "T_SQL_SELECT",
            self::T_SQL_UNION_ALL => "T_SQL_UNION_ALL",
            self::T_SQL_ANY => "T_SQL_ANY",
        );
        return $map[$code];
    }

    public static function is_type_of($sub_type, $type)
    {
        return $type % 1000 == 0 ? $sub_type - $sub_type % 1000 == $type : ($type % 100 == 0 ? $sub_type - $sub_type % 100 == $type : ($type % 10 == 0 ? $sub_type - $sub_type % 10 == $type : $sub_type == $type));
    }

    public static function is_token_of($token, $type)
    {
        if ($token === NULL) {
            return false;
        }
        if (!\is_int($type)) { //各种符号 或者 字符串
            if (is_array($token)) {
                return \strcasecmp($token[1], $type) === 0;
            } else {
                return \strcasecmp($token, $type) === 0;
            }
        }
        if (\is_array($token)) {
            $token = $token[0];
        }
        if (!is_numeric($token)) {
            return false;
        }
        return self::is_type_of($token, $type);
    }

    public static function is_expr_of($expr, $type)
    {
        if (!\is_int($type)) { //各种符号 或者 字符串
            return \strcasecmp($expr["expr"], $type) === 0;
        }
        return self::is_type_of($expr["type"], $type);
    }

    public static function join_tokens($tokens)
    {
        foreach ($tokens as &$token) {
            if (\is_array($token)) {
                $token = $token[1];
            }
        }
        return \implode($tokens);
    }

    protected static function convert_expr_name(&$expr)
    {
        if (!isset($expr)) {
            return;
        }
        switch ($expr["type"]) {
            case self::T_FUNCTION:
            case self::T_SQL_UNION_ALL:
                foreach ($expr["sub_tree"] as &$sub_expr) {
                    self::convert_expr_name($sub_expr);
                }
                break;
            case self::T_SUBQUERY:
                self::convert_expr_name($expr["sub_tree"]);
                break;
            case self::T_SQL_SELECT:
                foreach (["WITH", "SELECT", "FROM", "ARRAYJOIN", "GROUPBY", "ORDERBY"] as $key) {
                    if (isset($expr[$key])) {
                        foreach ($expr[$key] as &$sub_expr) {
                            self::convert_expr_name($sub_expr);
                        }
                    }
                }
                foreach (["PREWHERE", "WHERE", "HAVING"] as $key) {
                    if (isset($expr[$key])) {
                        self::convert_expr_name($expr[$key]);
                    }
                }
                break;
        }
        $expr["type"] = self::token_name($expr["type"]);
    }

    public static function dump_expr($expr, $return = false)
    {
        self::convert_expr_name($expr);
        $s = json_encode($expr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!$return) {
            echo $s;
        }
        return $s;
    }


    public static function dump_tokens($tokens, $return = false)
    {
        $s = "";
        foreach ($tokens as $k => &$token) {
            if (\is_array($token)) {
                $s .= "$k: (" . self::token_name($token[0]) . ") " . \var_export($token[1], true) . "\n";
            } else {
                $s .= "$k: " . \var_export($token, true) . "\n";
            }
        }
        if (!$return) {
            echo $s;
        }
        return $s;
    }

    protected static function token_to_string($token)
    {
        if ($token === NULL) {
            return "(EOF)";
        } elseif (!is_array($token)) {
            return $token;
        } else {
            return $token[1];
        }
    }

    protected static function generate_splitter_map_set_map($str, &$map)
    {
        if ($str === "") {
            $map[""] = true;
        } else {
            if (!isset($map[$str[0]])) {
                $map[$str[0]] = array();
            }
            self::generate_splitter_map_set_map(\substr($str, 1), $map[$str[0]]);
        }
    }

    protected static function generate_splitter_map($splitters)
    {
        $map = array();
        foreach ($splitters as $splitter) {
            self::generate_splitter_map_set_map($splitter, $map);
        }
        return $map;
    }

    protected static function is_valid_name_char($char)
    {
        $c = ord($char);
        return ($c >= 48 && $c <= 57) || ($c >= 65 && $c <= 90) || $c == 95 || ($c >= 97 && $c <= 122) || $c >= 127;
    }

    protected static function is_valid_name_start_char($char)
    {
        $c = ord($char);
        return ($c >= 65 && $c <= 90) || $c == 95 || ($c >= 97 && $c <= 122) || $c >= 127;
    }

    protected static function get_splitters_map()
    {
        static $splitters_map;
        if ($splitters_map === NULL) {
            $splitters = array(
                "<=>",
                "\r\n", "!=", ">=", "<=", "<>", "<<", ">>", ":=", "&&", "||", "@@", "->",
                "[", "]", //clickhouse array
                ">", "<", "!", "^", "&", "|", "=", "(", ")", "\t", "\r", "\n", " ", "@", ":", "+", "-", "*", "%", ";", ",", ".",
                "\\", "`", "'", "\""
                //support above and: / /* */ -- - ->
            );
            $splitters_map = self::generate_splitter_map($splitters);
        }
        return $splitters_map;
    }

    protected static function get_next_splitter($last, $str, $index, $map)
    {
        $c = @$str[$index];
        if ($c === "") {
            if (@$map[""] === true) {
                return array($last, $index);
            }
            return false;
        }
        if (isset($map[$c])) {
            $r = self::get_next_splitter($last . $c, $str, $index + 1, $map[$c]);
            if ($r) {
                return $r;
            }
        }
        if ($last === "") {
            return false;
        } elseif (@$map[""] === true) {
            return array($last, $index);
        }
        return false;
    }

    protected static function is_token_blank($token)
    {
        static $map = array(" " => 1, "\r" => 1, "\n" => 1, "\t" => 1, "\r\n" => 1);
        if (\is_array($token)) {
            return self::is_token_of($token, self::T_COMMENT) || self::is_token_of($token, self::T_WHITESPACE);
        } else {
            for ($i = \strlen($token); $i-- > 0;) {
                if (!isset($map[$token[$i]])) {
                    return false;
                }
            }
            return true;
        }
    }

    //start with [\\a-zA-Z_\x7f-\xff][
    protected static function get_next_string($str, $index)
    {
        $s = "";
        $c = @$str[$index];
        for (;;) {
            if (self::is_valid_name_char($c)) {
                $s .= $c;
                $c = @$str[++$index];
            } else {
                break;
            }
        }
        if (\strcasecmp("NULL", $s) == 0) {
            return array(array(self::T_CONSTANT_NULL, $s), $index);
        }
        return array(array(self::T_STRING_IDENTIFIER_NOQUOTE, $s), $index);
    }

    //start with [\'\"\`]
    protected static function get_next_quote($str, $index)
    {
        $s = $quote = $str[$index];
        $c = @$str[++$index];
        for (;;) {
            if ($c === "\\") {
                $s .= $c;
                $c = @$str[++$index];
                if ($c === "") {
                    break;
                }
                $s .= $c;
                $c = @$str[++$index];
            } elseif ($c === $quote) {
                $s .= $c;
                $map = array(
                    "\"" => self::T_STRING_IDENTIFIER_DOUBLE_QUOTE,
                    "'" => self::T_CONSTANT_ENCAPSED_STRING_SINGLE_QUOTE,
                    "`" => self::T_STRING_IDENTIFIER_BACKQUOTE,
                );
                return array(array($map[$quote], $s, NULL), $index + 1);
            } else {
                $s .= $c;
                $c = @$str[++$index];
            }
        }
        throw new \ErrorException("cannot find matched $quote in string $s");
    }

    //start with [\#\/]
    protected static function get_next_comment($str, $index)
    {
        if (@$str[$index] === "/" && @$str[$index + 1] === "*") {
            $s = $str[$index]; // /
            $s .= $str[++$index]; // *
            $c = @$str[++$index];
            for (;;) {
                if ($c === "*" && @$str[$index + 1] === "/") {
                    $s .= $c; // *
                    $s .= $str[++$index]; // /
                    ++$index;
                    break;
                } elseif ($c === "") {
                    break;
                } else {
                    $s .= $c;
                    $c = @$str[++$index];
                }
            }
            return array(array(self::T_COMMENT_MULTI_LINE, $s), $index);
        } elseif (@$str[$index] === "-" && @$str[$index + 1] === "-") {
            $s = "";
            $c = $str[$index];
            for (;;) {
                if ($c === "\r" || $c === "\n" || $c === "") {
                    break;
                } else {
                    $s .= $c;
                    $c = @$str[++$index];
                }
            }
            return array(array(self::T_COMMENT_SINGLE_LINE, $s), $index);
        } else {
            return array(false, $index);
        }
    }

    //start with [\t\r\n ]
    protected static function get_next_whitespace($str, $index)
    {
        static $map = array(" " => 1, "\r" => 1, "\n" => 1, "\t" => 1);
        $s = $c = $str[$index];
        for (;;) {
            if (isset($map[$c])) {
                $s .= $c;
                $c = @$str[++$index];
            } else {
                break;
            }
        }
        return array(array(self::T_WHITESPACE, $s), $index);
    }

    protected static function get_next_token($str, $index)
    {
        static $map = array(
            "`" => 1, "'" => 1, "\"" => 1,
            "-" => 2, "/" => 2,
            //"\$" => 3,
            "0" => 4, "1" => 4, "2" => 4, "3" => 4, "4" => 4, "5" => 4, "6" => 4, "7" => 4, "8" => 4, "9" => 4,
            " " => 5, "\t" => 5, "\r" => 5, "\n" => 5,
            "" => 6,
        );
        $c = @$str[$index];
        if (!isset($map[$c])) {
            DEF: if (self::is_valid_name_start_char($c)) {
                return self::get_next_string($str, $index);
            } else {
                if (($r = self::get_next_splitter("", $str, $index, self::get_splitters_map()))) {
                    return $r;
                } else {
                    \var_dump($str, $index);
                    throw new \ErrorException("unkown char $c (" . ord($c) . ")");
                }
            }
        }
        switch ($map[$c]) {
            case 1:
                list($token, $index) = self::get_next_quote($str, $index);
                break;
            case 2:
                list($token, $index) = self::get_next_comment($str, $index);
                break;
            case 4:
                list($token, $index) = self::get_next_number($str, $index);
                break;
            case 5:
                list($token, $index) = self::get_next_whitespace($str, $index);
                break;
            case 6:
                return array(false, $index); //END
            default:
                throw new \ErrorException("BUG");
        }
        if ($token === false) {
            goto DEF;
        }
        return array($token, $index);
    }

    protected static function get_next_number($str, $index)
    {
        static $map = array(
            "0" => 0, "1" => 1, "2" => 2, "3" => 3, "4" => 4, "5" => 5, "6" => 6, "7" => 7, "8" => 8, "9" => 9,
            "A" => 10, "a" => 10, "B" => 11, "b" => 11, "C" => 12, "c" => 12, "D" => 13, "d" => 13, "E" => 14, "e" => 14, "F" => 15, "f" => 15,
            "" => 16, "." => 17,
            "X" => 18, "x" => 18,
            "+" => 19, "-" => 20,
        );
        $is_float = false;
        $s = "";
        $state = 0;
        $c = @$str[$index];
        for (;;) {
            switch ($state) {
                case 0:
                    switch ($map[$c]) { //初始状态，可以接受数字和.输入
                        case 0:
                            $s .= $c;
                            $state = 1;
                            break;
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            $state = 2;
                            break;
                        default:
                            throw new \ErrorException("BUG");
                    }
                    $c = @$str[++$index];
                    break;
                case 1: //0~
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 14: //E
                            $s .= $c;
                            $state = 7;
                            break;
                        case 17: //.
                            $s .= $c;
                            $state = 8;
                            break;
                        case 16: // ''
                        default:
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                case 2: //[1-9]~
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 0:
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            break;
                        case 14: //E
                            $s .= $c;
                            $state = 7;
                            break;
                        case 17: // .
                            $s .= $c;
                            $state = 8;
                            break;
                        case 16: // ''
                        default:
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                case 7: //0E
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 0:
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            $state = 11;
                            break;
                        case 19:
                        case 20:
                            $s .= $c;
                            $state = 12;
                            break;
                        case 16:
                        default:
                            $index--;
                            $s = \substr($s, 0, -1);
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                case 8: //1.
                    $is_float = true;
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 0:
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            break;
                        case 14:
                            $s .= $c;
                            $state = 7;
                            break;
                        case 16: // ''
                        default:
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                case 11: //0E1
                    $is_float = true;
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 0:
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            break;
                        case 16:
                        default:
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                case 12: //0E+
                    $case = isset($map[$c]) ? $map[$c] : -1;
                    switch ($case) {
                        case 0:
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                        case 5:
                        case 6:
                        case 7:
                        case 8:
                        case 9:
                            $s .= $c;
                            $state = 11;
                            break;
                        case 16:
                        default:
                            $index -= 2;
                            $s = \substr($s, 0, -2);
                            break 3;
                    }
                    $c = @$str[++$index];
                    break;
                default:
                    throw new \ErrorException("BUG");
            }
        }
        return array(array($is_float ? self::T_CONSTANT_DNUMBER : self::T_CONSTANT_LNUMBER, $s), $index);
    }

    protected static function post_process_check_tokens($tokens)
    {
        $last_token = NULL;
        foreach ($tokens as $token) {
            if (self::is_token_of($token, self::T_CONSTANT) || self::is_token_of($token, self::T_STRING)) {
                if ($last_token !== NULL) {
                    throw new \ErrorException("unkown string {$token[1]} after {$last_token[1]}");
                } else {
                    $last_token = $token;
                }
            } else {
                $last_token = NULL;
            }
        }
        return $tokens;
    }

    protected static function post_process_remove_whitespace($tokens)
    {
        $new_tokens = array();
        foreach ($tokens as $token) {
            if (!self::is_token_blank($token)) {
                $new_tokens[] = $token;
            }
        }
        return $new_tokens;
    }

    public static function token_get_all($str)
    {
        if ($str === "") {
            return array();
        }
        $tokens = array();
        $index = 0;
        for (;;) {
            list($token, $index) = self::get_next_token($str, $index);
            if ($token === false) {
                break;
            } else {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    public static function parse($sql)
    {
        if (preg_match("{^[\s(]*(?:WITH|SELECT)\s}si", $sql)) {
            $tokens = self::token_get_all($sql);
            $tokens = self::post_process_check_tokens($tokens);
            $tokens = self::post_process_remove_whitespace($tokens);
            list($subquery, $index) = self::get_next_expr($tokens, 0, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            if (!self::is_expr_of($subquery, self::T_SUBQUERY)) {
                throw new \ErrorException("cannot parse as sql, not a subquery, maybe BUG");
            }
            return $subquery["sub_tree"];
        } else {
            return array("type" => self::T_SQL_ANY, "expr" => $sql);
        }
    }

    public static function parse_expr($str)
    {
        $tokens = self::token_get_all($str);
        $tokens = self::post_process_check_tokens($tokens);
        $tokens = self::post_process_remove_whitespace($tokens);
        list($expr, $index) = self::get_next_expr($tokens, 0, 0);
        if ($index != \count($tokens)) {
            throw new \ErrorException("cannot parse as expr, some token left");
        }
        return $expr;
    }

    protected static function aliasStr($p)
    {
        if (!isset($p["alias"]) || $p["alias"] === false || $p["alias"] === '') {
            return "";
        } else {
            return " AS " . $p["alias"];
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

    public static function create($p)
    {
        if (self::is_expr_of($p, self::T_CONSTANT)) {
            return $p["expr"] . self::aliasStr($p) . self::orderStr($p);
        } elseif (self::is_expr_of($p, self::T_STRING_IDENTIFIER)) {
            return $p["expr"] . self::aliasStr($p) . self::orderStr($p);
        } elseif (self::is_expr_of($p, self::T_EXP)) {
            return $p["expr"] . self::aliasStr($p) . self::orderStr($p);
        } elseif (self::is_expr_of($p, self::T_FUNCTION)) {
            $s = "";
            foreach (@$p["sub_tree"] ? $p["sub_tree"] : [] as $k => $sub) {
                if ($k !== 0) {
                    $s .= ",";
                }
                $s .= self::create($sub);
            }
            return $p["expr"] . "($s)" . self::aliasStr($p) . self::orderStr($p);
        } elseif (self::is_expr_of($p, self::T_SUBQUERY)) {
            return "(" . self::create($p["sub_tree"]) . ")" . self::aliasStr($p) . self::orderStr($p);
        } elseif (self::is_expr_of($p, self::T_SQL_ANY)) {
            return $p["expr"];
        } elseif (self::is_expr_of($p, self::T_SQL_UNION_ALL)) {
            $s = "";
            foreach ($p["sub_tree"] as $k => $query) {
                if ($k != 0) {
                    $s .= " UNION ALL ";
                }
                $s .= "(" . self::create($query) . ")";
            }
            return $s;
        } elseif (self::is_expr_of($p, self::T_SQL_SELECT)) {
            $s = "";
            if (@$p["WITH"]) {
                $s .= "WITH ";
                foreach ($p["WITH"] as $k => $exp) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .= self::create($exp);
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
            foreach ($p["SELECT"] as $k => $exp) {
                if ($k != 0) {
                    $s .= ",";
                }
                $s .= self::create($exp);
            }
            if (@$p["FROM"]) {
                $s .= " FROM";
                foreach ($p["FROM"] as $exp) {
                    $s .= " " . self::create($exp);
                }
            }
            if (@$p["PREWHERE"]) {
                $s .= " PREWHERE " . self::create($p["PREWHERE"]);
            }
            if (@$p["WHERE"]) {
                $s .= " WHERE " . self::create($p["WHERE"]);
            }
            if (@$p["GROUPBY"]) {
                $s .= " GROUP BY ";
                foreach ($p["GROUPBY"] as $k => $exp) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .= self::create($exp);
                }
            }
            if (@$p["HAVING"]) {
                $s .= " HAVING " . self::create($p["HAVING"]);
            }
            if (@$p["ORDERBY"]) {
                $s .= " ORDER BY ";
                foreach ($p["ORDERBY"] as $k => $exp) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .= self::create($exp);
                }
            }
            if (@$p["LIMITBY"]) {
                $s .= " LIMIT ";
                if (isset($p["LIMITBY"]["offset"])) {
                    $s .= $p["LIMITBY"]["offset"] . ",";
                }
                $s .= $p["LIMITBY"]["row_count"] . " BY ";
                foreach ($p["LIMITBY"]["expr_list"] as $k => $exp) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .= self::create($exp);
                }
            }
            if (@$p["LIMIT"]) {
                $s .= " LIMIT ";
                if (isset($p["LIMIT"]["offset"])) {
                    $s .= $p["LIMIT"]["offset"] . ",";
                }
                $s .= $p["LIMIT"]["row_count"];
            }
            return $s;
        } else {
            throw new \ErrorException("BUG");
        }
    }

    protected static function newExpr($type, $expr)
    {
        return array("type" => $type, "expr" => $expr);
    }

    protected static function tokenToExpr($token)
    {
        return array("type" => $token[0], "expr" => $token[1]);
    }

    protected static function T_FUNCTION($name, $sub_tree = [])
    {
        return array(
            "type" => self::T_FUNCTION,
            "expr" => $name,
            "sub_tree" => $sub_tree,
        );
    }

    protected static function get_next_expr($tokens, $index, $precedence)
    {
        $m2 = array( //2元操作符
            "OR" => "or", "AND" => "and",
            "=" => "equals", "!=" => "notEquals", "<>" => "notEquals", ">" => "greater", ">=" => "greaterOrEquals", "<" => "less", "<=" => "lessOrEquals",
            "LIKE" => "like",
            "+" => "plus", "-" => "minus", "*" => "multiply", "%" => "modulo", "/" => "divide"
        );
        $precedence_map = array(
            "or" => 1,
            "and" => 2,
            "not" => 3,
            "between" => 4,
            "notBetween" => 4,
            "equals" => 5,
            "notEquals" => 5,
            "greater" => 5,
            "greaterOrEquals" => 5,
            "less" => 5,
            "lessOrEquals" => 5,
            "is" => 5,
            "like" => 5,
            "notLike" => 5,
            "in" => 5,
            "notIn" => 5,
            "plus" => 6,
            "minus" => 6,
            "multiply" => 7,
            "divide" => 7,
            "modulo" => 7,
            "negate" => 8,
        );
        $token = @$tokens[$index];
        if (self::is_token_of($token, "-")) {
            list($val1, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map["negate"]);
            if (self::is_expr_of($val1, self::T_CONSTANT_NUMBER) && $val1["expr"][0] !== "-") {
                $val1["expr"] = "-" . $val1["expr"];
            } else {
                $val1 = self::T_FUNCTION("negate", [$val1]);
            }
        } elseif (self::is_token_of($token, "(")) {
            $val1 = self::newExpr(self::T_STRING_IDENTIFIER_NOQUOTE, ""); //然后会出来一个空函数
        } elseif (self::is_token_of($token, "[")) {
            $token = @$tokens[++$index];
            $sub_tree = array();
            if ($token !== "]") {
                for (;;) {
                    list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                    $sub_tree[] = $exp;
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
            $val1 = self::T_FUNCTION("array", $sub_tree);
        } elseif (self::is_token_of($token, self::T_CONSTANT)) {
            $val1 = self::tokenToExpr($token);
            $index++;
        } elseif (self::is_token_of($token, "NOT")) {
            list($val1, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map["not"]);
            $val1 = self::T_FUNCTION("not", [$val1]);
        } elseif (self::is_token_of($token, "WITH") || self::is_token_of($token, "SELECT")) {
            list($val1, $index) = self::get_next_select($tokens, $index);
            $val1 = array("type" => self::T_SUBQUERY, "expr" => "", "sub_tree" => $val1);
        } elseif (self::is_token_of($token, self::T_STRING_IDENTIFIER)) { //SELECT/WITH
            $val1 = self::tokenToExpr($token);
            $index++;
        } else {
            throw new \ErrorException("unexpect token " . self::token_to_string($token));
        }
        for (;;) {
            if (\count($tokens) <= $index) {
                return array($val1, $index);
            }
            $token = @$tokens[$index];
            if (!\is_array($token) || self::is_token_of($token, "and") || self::is_token_of($token, "or")) {
                if ($token === ")" || $token === "," || $token === "]") {
                    return array($val1, $index);
                } elseif ($token == "(") {
                    $token = @$tokens[++$index];
                    $sub_tree = array();
                    if ($token !== ")") {
                        for (;;) {
                            list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                            $token = @$tokens[$index];
                            if ($token === ")") {
                                $sub_tree[] = $exp;
                                $index++;
                                break;
                            } elseif ($token === ",") {
                                $sub_tree[] = $exp;
                                $index++;
                            } else {
                                throw new \ErrorException("expect ) or , got " . self::token_to_string($token));
                            }
                        }
                    } else {
                        $index++;
                    }
                    if (!self::is_expr_of($val1, self::T_STRING_IDENTIFIER_NOQUOTE)) {
                        throw new \ErrorException("expect FUNCTION NAME got " . self::create($val1));
                    }
                    if ($val1["expr"] === "") {
                        if (\count($sub_tree) == 0) {
                            throw new \ErrorException("unexpect token ()");
                        } elseif (\count($sub_tree) == 1) {
                            $val1 = $sub_tree[0];
                        } else {
                            $val1 = self::T_FUNCTION('tuple', $sub_tree);
                        }
                    } else {
                        $val1 = self::T_FUNCTION($val1["expr"], $sub_tree); //出来 (1+2)
                    }
                    continue;
                } elseif ($token == ".") {
                    $token = @$tokens[++$index];
                    if ($token === NULL) {
                        throw new \ErrorException("expect IDENTIFIER got EOF");
                    }
                    if (!self::is_expr_of($val1, self::T_STRING_IDENTIFIER)) {
                        throw new \ErrorException("expect IDENTIFIER got " . self::create($val1));
                    }
                    if (self::is_token_of($token, self::T_CONSTANT_LNUMBER)) {
                        $val1 = self::T_FUNCTION("tupleElement", array($val1, self::tokenToExpr($token)));
                        $index++;
                        continue;
                    } elseif (self::is_token_of($token, self::T_STRING_IDENTIFIER)) {
                        $val1 = self::newExpr(self::T_STRING_IDENTIFIER_COLREF, "{$val1["expr"]}.{$token[1]}");
                        $index++;
                        continue;
                    } else {
                        throw new \ErrorException("expect IDENTIFIER got " . self::token_to_string($token));
                    }
                } elseif ($token == "[") {
                    list($exp, $index) = self::get_next_expr($tokens, $index + 1, 0);
                    $val1 = self::T_FUNCTION("arrayElement", array($val1, $exp));
                    $token = @$tokens[$index];
                    if ($token !== "]") {
                        throw new \ErrorException("expect ] got " . self::token_to_string($token));
                    }
                    $index++;
                    continue;
                }
                if (self::is_token_of($token, "and")) {
                    $token = "AND";
                } elseif (self::is_token_of($token, "or")) {
                    $token = "OR";
                }
                $operator = @$m2[$token];
                if ($operator === NULL) {
                    throw new \ErrorException("operator $token is not supported");
                }
                if ($precedence_map[$operator] <= $precedence) {
                    return array($val1, $index);
                } else {
                    $token = @$tokens[++$index];
                    if ($token === NULL) {
                        throw new \ErrorException("expect (EXPR) got " . self::token_to_string($token));
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index, $precedence_map[$operator]);
                    $val1 = self::T_FUNCTION($operator, array($val1, $val2));
                    continue;
                }
            } elseif (self::is_token_of($token, "AS")) {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, self::T_STRING_IDENTIFIER)) {
                    throw new \ErrorException("expect IDENTIFIER got " . self::token_to_string($token));
                }
                if (isset($val1["alias"])) {
                    throw new \ErrorException("already has alias, cannot alias twice " . self::create($val1));
                }
                $val1["alias"] = $token[1];
                $index++;
                continue;
            } elseif (self::is_token_of($token, "IS")) { // is null / is not null
                if ($precedence_map["is"] <= $precedence) {
                    return array($val1, $index);
                }
                $token = @$tokens[++$index];
                if (self::is_token_of($token, "NULL")) {
                    $val1 = self::T_FUNCTION("isNull", array($val1));
                    $index++;
                    continue;
                } elseif (self::is_token_of($token, "NOT")) {
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "NULL")) {
                        $val1 = self::T_FUNCTION("isNotNull", array($val1));
                        $index++;
                        continue;
                    } else {
                        throw new \ErrorException("expect 'NULL' after 'IS NOT' got " . self::token_to_string($token));
                    }
                } else {
                    throw new \ErrorException("expect 'NULL' or 'NOT NULL' after 'IS' got " . self::token_to_string($token));
                }
            } elseif (self::is_token_of($token, "NOT") || self::is_token_of($token, "IN") ||  self::is_token_of($token, "BETWEEN") || self::is_token_of($token, "LIKE")) {
                $old_index = $index;
                $not = false;
                if (self::is_token_of($token, "NOT")) {
                    $not = true;
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "IN") && !self::is_token_of($token, "BETWEEN") && !self::is_token_of($token, "LIKE")) {
                        throw new \ErrorException("expect 'IN' or 'BETWEEN' or 'LIKE' after 'NOT' got " . self::token_to_string($token));
                    }
                }
                if (self::is_token_of($token, "IN")) {
                    $operator = $not ? "notIn" : "in";
                    if ($precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    $token = @$tokens[++$index]; //(
                    $token = @$tokens[++$index];
                    $sub_tree = array();
                    if ($token === ")") {
                        throw new \ErrorException("in-list cannot be empty");
                    }
                    for (;;) {
                        list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                        $sub_tree[] = $exp;
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
                    $val1 = self::T_FUNCTION($operator, array($val1, self::T_FUNCTION("tuple", $sub_tree)));
                } elseif (!$not && self::is_token_of($token, "LIKE")) {
                    $operator = "like";
                    if ($precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $val1 = self::T_FUNCTION("like", array($val1, $val2));
                    continue;
                } elseif ($not && self::is_token_of($token, "LIKE")) {
                    $operator = "notLike";
                    if ($precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $val1 = self::T_FUNCTION("notLike", array($val1, $val2));
                } elseif (!$not && self::is_token_of($token, "BETWEEN")) {
                    $operator = "between";
                    if ($precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $token = @$tokens[$index];
                    if (!self::is_token_of($token, "AND")) {
                        throw new \ErrorException("expect 'AND' after 'BETWEEN ... ' got " . self::token_to_string($token));
                    }
                    list($val3, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $val1 = self::T_FUNCTION("and", array(
                        self::T_FUNCTION("greaterOrEquals", array($val1, $val2)),
                        self::T_FUNCTION("lessOrEquals", array($val1, $val3))
                    ));
                    continue;
                } elseif ($not && self::is_token_of($token, "BETWEEN")) {
                    $operator = "notBetween";
                    if ($precedence_map[$operator] <= $precedence) {
                        return array($val1, $old_index);
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $token = @$tokens[$index];
                    if (!self::is_token_of($token, "AND")) {
                        throw new \ErrorException("expect 'AND' after 'BETWEEN ... ' got " . self::token_to_string($token));
                    }
                    list($val3, $index) = self::get_next_expr($tokens, $index + 1, $precedence_map[$operator]);
                    $val1 = self::T_FUNCTION("or", array(
                        self::T_FUNCTION("less", array($val1, $val2)),
                        self::T_FUNCTION("greater", array($val1, $val3))
                    ));
                    continue;
                } else {
                    throw new \ErrorException("BUG");
                }
            } elseif (self::is_expr_of($val1, self::T_SUBQUERY) && self::is_token_of($token, "UNION")) {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, "ALL")) {
                    throw new \ErrorException("expect 'ALL' after 'UNION' got " . self::token_to_string($token));
                }
                list($val2, $index) = self::get_next_expr($tokens, $index + 1, 0);
                if (!self::is_expr_of($val2, self::T_SUBQUERY)) {
                    throw new \ErrorException("expect 'UNION ALL' must in SUBQUERY");
                }
                $query = $val1["sub_tree"];
                if ($query["type"] == self::T_SQL_SELECT) {
                    $query = array("type" => self::T_SQL_UNION_ALL, "sub_tree" => [$query]);
                }
                $query2 = $val2["sub_tree"];
                if ($query2["type"] == self::T_SQL_SELECT) {
                    $query2 = array("type" => self::T_SQL_UNION_ALL, "sub_tree" => [$query2]);
                }
                foreach ($query2["sub_tree"] as $q) {
                    $query["sub_tree"][] = $q;
                }
                $val1["sub_tree"] = $query;
                continue;
            } else {
                return array($val1, $index);
            }
        }
        throw new \ErrorException("BUG");
    }

    protected static function get_next_select($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_SELECT;
        $obj["SELECT"] = array();
        //$obj["PREWHERE"]=array();
        //$obj["WHERE"]=array();
        $token = @$tokens[$index];
        if (self::is_token_of($token, "WITH")) {
            $obj["WITH"] = array();
            $index++;
            for (;;) {
                list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                $obj["WITH"][] = $exp;
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
            list($exp, $index) = self::get_next_expr($tokens, $index, 0);
            $obj["SELECT"][] = $exp;
            $token = @$tokens[$index];
            if ($token === ",") {
                $index++;
            } else {
                break;
            }
        }
        $token = @$tokens[$index]; //FROM
        if (self::is_token_of($token, "FROM")) { //TODO array join
            $obj["FROM"] = array();
            list($exp, $index) = self::get_next_expr($tokens, $index + 1, 0);
            if (self::is_expr_of($exp, self::T_STRING_IDENTIFIER)) {
                $exp["type"] = self::T_STRING_IDENTIFIER_TABLE;
            }
            $obj["FROM"][] = $exp;
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "ARRAY")) { //TODO array join
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "JOIN")) {
                throw new \ErrorException("expect 'JOIN' after 'ARRAY' got " . self::token_to_string($token));
            }
            $index++;
            $obj["ARRAYJOIN"] = array();
            for (;;) {
                list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                $obj["ARRAYJOIN"][] = $exp;
                $token = @$tokens[$index];
                if ($token === ",") {
                    $index++;
                } else {
                    break;
                }
            }
        }

        $token = @$tokens[$index];
        if (self::is_token_of($token, "PREWHERE")) {
            list($exp, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["PREWHERE"] = $exp;
        }
        $token = @$tokens[$index];
        if (self::is_token_of($token, "WHERE")) {
            list($exp, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["WHERE"] = $exp;
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
                list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                $obj["GROUPBY"][] = $exp;
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
            list($exp, $index) = self::get_next_expr($tokens, $index + 1, 0);
            $obj["HAVING"] = $exp;
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
                list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                $token = @$tokens[$index];
                if (self::is_token_of($token, "ASC")) {
                    $exp["order"] = "ASC";
                    $token = @$tokens[++$index];
                } elseif (self::is_token_of($token, "DESC")) {
                    $exp["order"] = "DESC";
                    $token = @$tokens[++$index];
                }
                $obj["ORDERBY"][] = $exp;
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
                    list($exp, $index) = self::get_next_expr($tokens, $index, 0);
                    $expr_list[] = $exp;
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
        return array($obj, $index);
    }
}


