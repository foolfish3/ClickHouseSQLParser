<?php

namespace ClickHouseSQLParser;

class ClickHouseSQLParserTypes
{
    const T_BLANK = 1000;
    const T_COMMENT = 1010;
    const T_COMMENT_SINGLE_LINE = 1011;
    const T_COMMENT_MULTI_LINE = 1012;
    const T_WHITESPACE = 1100;
    const T_CONSTANT = 2100;
    const T_CONSTANT_NULL = 2110;
    const T_CONSTANT_NUMBER = 2120;
    const T_CONSTANT_LNUMBER = 2121;
    const T_CONSTANT_DNUMBER = 2122;
    const T_CONSTANT_STRING  = 2131;
    const T_IDENTIFIER = 3100;
    const T_IDENTIFIER_ASTERISK = 3150;
    const T_IDENTIFIER_TABLE = 3140;
    const T_IDENTIFIER_COLREF = 3130;
    const T_IDENTIFIER_NOQUOTE = 3110;
    const T_IDENTIFIER_QUOTE = 3120;
    const T_IDENTIFIER_BACKQUOTE = 3121;
    const T_IDENTIFIER_DOUBLEQUOTE = 3122;
    const T_INVALID_CHAR = 4000;
    const T_EXP_ANY = 5000;
    const T_FUNCTION = 6100;
    const T_PARAMETRIC_FUNCTION = 6200;
    const T_SUBQUERY = 7100;
    const T_SUBEXP   = 7200;
    const T_SQL = 9000;
    const T_SQL_ALLOW_IN_SUBQUERY     = 9100; //allowed in subquery
    const T_SQL_SELECT    = 9101;
    const T_SQL_UNION_ALL = 9102;
    const T_SQL_ANY       = 9999;

    protected static $type_child_map = array(
        self::T_BLANK => array(
            self::T_COMMENT => 1,
            self::T_COMMENT_SINGLE_LINE => 1,
            self::T_COMMENT_MULTI_LINE => 1,
            self::T_WHITESPACE => 1,
        ),
        self::T_COMMENT => array(
            self::T_COMMENT_SINGLE_LINE => 1,
            self::T_COMMENT_MULTI_LINE => 1,
        ),
        self::T_CONSTANT => array(
            self::T_CONSTANT_NULL => 1,
            self::T_CONSTANT_NUMBER => 1,
            self::T_CONSTANT_LNUMBER => 1,
            self::T_CONSTANT_DNUMBER => 1,
            self::T_CONSTANT_STRING => 1,
        ),
        self::T_CONSTANT_NUMBER => array(
            self::T_CONSTANT_LNUMBER => 1,
            self::T_CONSTANT_DNUMBER => 1,
        ),
        self::T_IDENTIFIER => array(
            self::T_IDENTIFIER_ASTERISK => 1,
            self::T_IDENTIFIER_TABLE => 1,
            self::T_IDENTIFIER_COLREF => 1,
            self::T_IDENTIFIER_NOQUOTE => 1,
            self::T_IDENTIFIER_QUOTE => 1,
            self::T_IDENTIFIER_BACKQUOTE => 1,
            self::T_IDENTIFIER_DOUBLEQUOTE => 1,
        ),
        self::T_IDENTIFIER_QUOTE => array(
            self::T_IDENTIFIER_BACKQUOTE => 1,
            self::T_IDENTIFIER_DOUBLEQUOTE => 1,
        ),
        self::T_SQL => array(
            self::T_SQL_ALLOW_IN_SUBQUERY => 1,
            self::T_SQL_SELECT => 1,
            self::T_SQL_UNION_ALL => 1,
            self::T_SQL_ANY => 1,
        ),
        self::T_SQL_ALLOW_IN_SUBQUERY => array(
            self::T_SQL_SELECT => 1,
            self::T_SQL_UNION_ALL => 1,
        ),
    );

    protected static $type_name_map = array(
        self::T_BLANK => "T_BLANK",
        self::T_COMMENT => "T_COMMENT",
        self::T_COMMENT_SINGLE_LINE => "T_COMMENT_SINGLE_LINE",
        self::T_COMMENT_MULTI_LINE => "T_COMMENT_MULTI_LINE",
        self::T_WHITESPACE => "T_WHITESPACE",
        self::T_CONSTANT => "T_CONSTANT",
        self::T_CONSTANT_NULL => "T_CONSTANT_NULL",
        self::T_CONSTANT_NUMBER => "T_CONSTANT_NUMBER",
        self::T_CONSTANT_LNUMBER => "T_CONSTANT_LNUMBER",
        self::T_CONSTANT_DNUMBER => "T_CONSTANT_DNUMBER",
        self::T_CONSTANT_STRING => "T_CONSTANT_STRING",
        self::T_IDENTIFIER => "T_IDENTIFIER",
        self::T_IDENTIFIER_TABLE => "T_IDENTIFIER_TABLE",
        self::T_IDENTIFIER_ASTERISK => "T_IDENTIFIER_ASTERISK",
        self::T_IDENTIFIER_COLREF => "T_IDENTIFIER_COLREF",
        self::T_IDENTIFIER_NOQUOTE => "T_IDENTIFIER_NOQUOTE",
        self::T_IDENTIFIER_QUOTE => "T_IDENTIFIER_QUOTE",
        self::T_IDENTIFIER_BACKQUOTE => "T_IDENTIFIER_BACKQUOTE",
        self::T_IDENTIFIER_DOUBLEQUOTE => "T_IDENTIFIER_DOUBLEQUOTE",
        self::T_INVALID_CHAR => "T_INVALID_CHAR",
        self::T_EXP_ANY => "T_EXP_ANY",
        self::T_FUNCTION => "T_FUNCTION",
        self::T_PARAMETRIC_FUNCTION => "T_PARAMETRIC_FUNCTION",
        self::T_SUBQUERY => "T_SUBQUERY",
        self::T_SUBEXP => "T_SUBEXP",
        self::T_SQL => "T_SQL",
        self::T_SQL_ALLOW_IN_SUBQUERY => "T_SQL_ALLOW_IN_SUBQUERY",
        self::T_SQL_SELECT => "T_SQL_SELECT",
        self::T_SQL_UNION_ALL => "T_SQL_UNION_ALL",
        self::T_SQL_ANY => "T_SQL_ANY",
    );

    public static function type_name($code)
    {
        return self::$type_name_map[$code];
    }

    public static function is_type_of($sub_type, $type)
    {
        return $sub_type === $type || isset(self::$type_child_map[$type][$sub_type]);
    }

    public static function is_token_of($token, $type)
    {
        if ($token === NULL) {
            return false;
        }
        if (!\is_int($type)) { //operator keywords
            if (\is_array($token)) {
                return \strcasecmp($token[1], $type) === 0;
            } else {
                return \strcasecmp($token, $type) === 0;
            }
        }
        if (\is_array($token)) {
            $token = $token[0];
        } else {
            return false;
        }
        return self::is_type_of($token, $type);
    }

    public static function is_expr_of($expr, $type)
    {
        return self::is_type_of($expr["type"], $type);
    }

    public static function is_expr_of_function($expr, $func)
    {
        return self::is_type_of($expr["type"], self::T_FUNCTION) && $expr["expr"] === $func;
    }

    public static $EXP_CONSTANT_NULL = array(
        "type" => self::T_CONSTANT_NULL,
        "expr" => "",
    );

    public static function EXP_CONSTANT_LNUMBER($num)
    {
        return array(
            "type" => self::T_CONSTANT_LNUMBER,
            "expr" => \strval($num),
        );
    }

    public static function EXP_CONSTANT_DNUMBER($num)
    {
        return array(
            "type" => self::T_CONSTANT_DNUMBER,
            "expr" => \strval($num),
        );
    }

    public static function EXP_CONSTANT_STRING($str)
    {
        return array(
            "type" => self::T_CONSTANT_STRING,
            "expr" => \strval($str),
        );
    }

    public static function EXP_SUBEXP($expr)
    {
        return array(
            "type" => self::T_SUBEXP,
            "expr" => "",
            "sub_tree" => $expr,
        );
    }

    public static $EXP_CONSTANT_0 = array(
        "type" => self::T_CONSTANT_LNUMBER,
        "expr" => "0",
    );


    public static $EXP_CONSTANT_1 = array(
        "type" => self::T_CONSTANT_LNUMBER,
        "expr" => "1",
    );

    public static $EXP_CONSTANT_EMPTY_STRING = array(
        "type" => self::T_CONSTANT_STRING,
        "expr" => "",
    );

    public static $EXP_IDENTIFIER_ASTERISK = array(
        "type" => self::T_IDENTIFIER_ASTERISK,
        "expr" => "",
    );

    public static function EXP_IDENTIFIER_COLREF($parts)
    {
        if (!is_array($parts)) {
            $parts = self::parse_colref($parts);
        }
        if (\count($parts) === 0) {
            throw new \ErrorException("colref cannot be empty");
        }
        return array(
            "type" => self::T_IDENTIFIER_COLREF,
            "expr" => "",
            "parts" => $parts,
        );
    }

    public static function EXP_IDENTIFIER_TABLE($parts)
    {
        if (!is_array($parts)) {
            $parts = self::parse_colref($parts);
        }
        if (\count($parts) === 0) {
            throw new \ErrorException("table cannot be empty");
        }
        return array(
            "type" => self::T_IDENTIFIER_TABLE,
            "expr" => "",
            "parts" => $parts,
        );
    }

    public static function EXP_FUNCTION($name, $sub_tree)
    {
        return array(
            "type" => self::T_FUNCTION,
            "expr" => $name,
            "sub_tree" => $sub_tree,
        );
    }
    public static function EXP_PARAMETRIC_FUNCTION($name, $sub_tree)
    {
        return array(
            "type" => self::T_PARAMETRIC_FUNCTION,
            "expr" => $name,
            "sub_tree" => $sub_tree,
        );
    }

    public static function EXP_ANY($str)
    {
        return array(
            "type" => self::T_EXP_ANY,
            "expr" => $str,
        );
    }

    public static function EXP_SUBQUERY($expr)
    {
        unset($expr["FORMAT"]);
        return array(
            "type" => self::T_SUBQUERY,
            "expr" => "",
            "sub_tree" => $expr,
        );
    }

    public static function SQL_ANY($str)
    {
        return array(
            "type" => self::T_SQL_ANY,
            "expr" => $str,
        );
    }

    public static function SQL_UNION_ALL($sub_tree)
    {
        return array(
            "type" => self::T_SQL_UNION_ALL,
            "expr" => "",
            "sub_tree" => $sub_tree,
        );
    }

    public static function is_expr_const_true($p)
    {
        return self::is_expr_of($p, self::T_CONSTANT_LNUMBER) && $p["expr"] !== "0";
    }

    public static function is_expr_const_false($p)
    {
        return self::is_expr_of($p, self::T_CONSTANT_LNUMBER) && $p["expr"] === "0";
    }

    public static function is_expr_const_empty_string($p)
    {
        return self::is_expr_of($p, self::T_CONSTANT_STRING) && $p["expr"] === "";
    }


    public static function token_to_constant_expr($token)
    {
        switch ($token[0]) {
            case self::T_CONSTANT_NULL:
                return self::$EXP_CONSTANT_NULL;
            case self::T_CONSTANT_LNUMBER:
                return  self::EXP_CONSTANT_LNUMBER($token[1]);
            case self::T_CONSTANT_DNUMBER:
                return  self::EXP_CONSTANT_DNUMBER($token[1]);
            case self::T_CONSTANT_STRING:
                return self::EXP_CONSTANT_STRING(self::mysql_decode_str($token[1]));
            default:
                throw new \ErrorException("BUG");
        }
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
                $s .= "$k: (" . self::type_name($token[0]) . ") " . \var_export($token[1], true) . "\n";
            } else {
                $s .= "$k: " . \var_export($token, true) . "\n";
            }
        }
        if (!$return) {
            echo $s;
        }
        return $s;
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
        switch ($expr["type"]) {
            case self::T_FUNCTION:
            case self::T_SQL_UNION_ALL:
                foreach (["SETTINGS"] as $key) {
                    if (isset($expr[$key])) {
                        foreach ($expr[$key] as &$sub_expr) {
                            self::convert_expr_name($sub_expr);
                        }
                    }
                }
                foreach ($expr["sub_tree"] as &$sub_expr) {
                    self::convert_expr_name($sub_expr);
                }
                break;
            case self::T_PARAMETRIC_FUNCTION:
                foreach ($expr["sub_tree"] as &$a) {
                    foreach ($a as &$sub_expr) {
                        self::convert_expr_name($sub_expr);
                    }
                }
                break;
            case self::T_SUBEXP:
            case self::T_SUBQUERY:
                self::convert_expr_name($expr["sub_tree"]);
                break;
            case self::T_SQL_SELECT:
                foreach (["WITH", "SELECT", "FROM", "ARRAYJOIN", "GROUPBY", "ORDERBY", "SETTINGS"] as $key) {
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
        foreach (["using"] as $key) {
            if (isset($expr[$key])) {
                foreach ($expr[$key] as &$sub_expr) {
                    self::convert_expr_name($sub_expr);
                }
            }
        }
        foreach (["on"] as $key) {
            if (isset($expr[$key])) {
                self::convert_expr_name($expr[$key]);
            }
        }
        $expr["type"] = self::type_name($expr["type"]);
    }

    public static function parse_colref($str)
    {
        static $map = array("0" => "\0", "n" => "\n", "r" => "\r", "\\" => "\\", "'" => "'", "\"" => "\"", "Z" => "\032", "`" => "`");
        $index = 0;
        $ss = array();
        $s = "";
        $c = @$str[$index];
        $quote = NULL;
        for (;;) {
            switch ($c) {
                case "\"":
                case "`":
                    if ($quote !== NULL) {
                        throw new \ErrorException("cannot parse $str");
                    }
                    $quote = $c;
                    $c = @$str[++$index];
                    for (;;) {
                        switch ($c) {
                            case $quote:
                                if ($s === "") {
                                    throw new \ErrorException("cannot parse $str");
                                }
                                $c = @$str[++$index];
                                break 2;
                            case "\\":
                                $c = @$str[++$index];
                                if ($c === "") {
                                    throw new \ErrorException("cannot parse $str");
                                }
                                if (!isset($map[$c])) {
                                    throw new \ErrorException("cannot parse $str");
                                }
                                $s .= $map[$c];
                                $c = @$str[++$index];
                                break;
                            case "":
                                throw new \ErrorException("cannot parse $str");
                            default:
                                $s .= $c;
                                $c = @$str[++$index];
                        }
                    }
                    break;
                case ".":
                    if ($s === "") {
                        throw new \ErrorException("cannot parse $str");
                    }
                    $ss[] = $s;
                    $s = "";
                    $quote = NULL;
                    $c = @$str[++$index];
                    break;
                case " ":
                    $c = @$str[++$index];
                    break;
                case "":
                    if ($s === "") {
                        throw new \ErrorException("cannot parse $str");
                    }
                    $ss[] = $s;
                    return $ss;
                default:
                    if ($quote !== NULL) {
                        throw new \ErrorException("cannot parse $str");
                    }
                    $s .= $c;
                    $c = @$str[++$index];
            }
        }
        throw new \ErrorException("BUG");
    }

    public static function backquote($str)
    {
        if (\is_array($str)) {
            $ss = array();
            foreach ($str as $s) {
                $ss[] = self::backquote($s);
            }
            return \implode(",", $ss);
        }
        return "`" . \strtr($str, array("\000" => "\\0", "\n" => "\\n", "\r" => "\\r", "\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "`" => "\`")) . "`";
    }

    public static function mysql_decode_str($str)
    {
        if (@$str[0] === "'") {
            return \strtr(substr($str, 1, -1), array("\\0" => "\0", "\\n" => "\n", "\\r" => "\r", "\\\\" => "\\", "\\'" => "'", "\\\"" => "\"", "\\Z" => "\032"));
        } else {
            return \strcasecmp($str, "NULL") ? $str : NULL;
        }
    }

    public static function mysql_encode_str($str, $noquote = 0)
    {
        if ($str === NULL) {
            return "NULL";
        } else {
            return $noquote ? $str : "'" . \strtr($str, array("\000" => "\\0", "\n" => "\\n", "\r" => "\\r", "\\" => "\\\\", "'" => "\\'", "\"" => "\\\"")) . "'";
        }
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
    protected function __construct()
    {
    }
}
