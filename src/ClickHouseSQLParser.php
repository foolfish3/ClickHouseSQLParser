<?php

namespace ClickHouseSQLParser;

class ClickHouseSQLParser
{
    protected function __construct()
    {
    }

    const T_BLANK = 1000;
    const T_COMMENT = 1010;
    const T_COMMENT_SINGLE_LINE = 1011; //TOKENS
    const T_COMMENT_MULTI_LINE = 1012; //TOKENS
    const T_WHITESPACE = 1100; //TOKENS
    const T_CONSTANT = 2100;
    const T_CONSTANT_NULL = 2110; //EXP
    const T_CONSTANT_NUMBER = 2120;
    const T_CONSTANT_LNUMBER = 2121; //EXP //TOKENS
    const T_CONSTANT_DNUMBER = 2122; //EXP //TOKENS
    const T_CONSTANT_STRING  = 2131; //EXP //TOKENS
    const T_IDENTIFIER = 3100;
    const T_IDENTIFIER_ASTERISK = 3160; //EXP
    const T_IDENTIFIER_DATABASE = 3150; //EXP
    const T_IDENTIFIER_TABLE = 3140; //EXP
    const T_IDENTIFIER_COLREF = 3130; //EXP
    const T_IDENTIFIER_NOQUOTE = 3110; //TOKENS
    const T_IDENTIFIER_QUOTE = 3120;
    const T_IDENTIFIER_BACKQUOTE = 3121; //TOKENS
    const T_IDENTIFIER_DOUBLEQUOTE = 3122; //TOKENS
    const T_INVALID_CHAR = 4000; //TOKENS
    const T_EXP_ANY = 5000; //EXP
    const T_FUNCTION = 6100; //EXP
    const T_PARAMETRIC_FUNCTION = 6200; //EXP
    const T_TABLE_FUNCTION = 6300; //EXP
    const T_SUBQUERY = 7100; //EXP
    const T_SUBEXP   = 7200; //EXP
    const T_SQL_SELECT_OR_UNION_ALL     = 9100; //allowed in subquery
    const T_SQL_SELECT    = 9101; //EXP
    const T_SQL_UNION_ALL = 9102; //EXP
    const T_SQL_ANY       = 9999; //EXP

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
            self::T_IDENTIFIER_DATABASE => 1,
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
        self::T_SQL_SELECT_OR_UNION_ALL => array(
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
        self::T_IDENTIFIER_DATABASE => "T_IDENTIFIER_DATABASE",
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
        self::T_TABLE_FUNCTION => "T_TABLE_FUNCTION",
        self::T_SUBQUERY => "T_SUBQUERY",
        self::T_SUBEXP => "T_SUBEXP",
        self::T_SQL_SELECT_OR_UNION_ALL => "T_SQL_SELECT_OR_UNION_ALL",
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
        if (\is_array($func)) {
            return self::is_type_of($expr["type"], self::T_FUNCTION) && isset($func[$expr["expr"]]);
        } else {
            return self::is_type_of($expr["type"], self::T_FUNCTION) && $expr["expr"] === $func;
        }
    }

    protected static $EXP_CONSTANT_NULL = array(
        "type" => self::T_CONSTANT_NULL,
        "expr" => "",
    );

    public static function EXP_CONSTANT_NULL()
    {
        return self::$EXP_CONSTANT_NULL;
    }

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

    public static function getConstantValue($expr)
    {
        if (!self::is_expr_of($expr, self::T_CONSTANT)) {
            throw new \ErrorException("not a T_CONSTANT");
        }
        if (self::is_expr_of($expr, self::T_CONSTANT_NULL)) {
            return NULL;
        } else {
            return $expr["expr"];
        }
    }

    public static function EXP_SUBEXP($expr)
    {
        return array(
            "type" => self::T_SUBEXP,
            "expr" => "",
            "sub_tree" => $expr,
        );
    }

    protected static $EXP_CONSTANT_0 = array(
        "type" => self::T_CONSTANT_LNUMBER,
        "expr" => "0",
    );

    public static function EXP_CONSTANT_0()
    {
        return self::$EXP_CONSTANT_0;
    }

    protected static $EXP_CONSTANT_1 = array(
        "type" => self::T_CONSTANT_LNUMBER,
        "expr" => "1",
    );

    public static function EXP_CONSTANT_1()
    {
        return self::$EXP_CONSTANT_1;
    }

    protected static $EXP_CONSTANT_EMPTY_STRING = array(
        "type" => self::T_CONSTANT_STRING,
        "expr" => "",
    );

    public static function EXP_CONSTANT_EMPTY_STRING()
    {
        return self::$EXP_CONSTANT_EMPTY_STRING;
    }

    public static $EXP_IDENTIFIER_ASTERISK = array(
        "type" => self::T_IDENTIFIER_ASTERISK,
        "expr" => "",
    );

    public static function EXP_IDENTIFIER_ASTERISK()
    {
        return self::$EXP_IDENTIFIER_ASTERISK;
    }

    public static function EXP_IDENTIFIER_COLREF($parts_or_str)
    {
        if (\is_string($parts_or_str)) {
            $parts = self::parse_colref($parts_or_str);
        } else {
            $parts = $parts_or_str;
        }
        if (\count($parts) === 0) {
            throw new \ErrorException("T_IDENTIFIER_COLREF cannot be empty");
        }
        return array(
            "type" => self::T_IDENTIFIER_COLREF,
            "expr" => "",
            "parts" => $parts,
        );
    }


    public static function EXP_IDENTIFIER_DATABASE($parts_or_str)
    {
        if (\is_string($parts_or_str)) {
            $parts = self::parse_colref($parts_or_str);
        } else {
            $parts = $parts_or_str;
        }
        if (\count($parts) === 0) {
            throw new \ErrorException("T_IDENTIFIER_DATABASE cannot be empty");
        } elseif (\count($parts) === 1) {
            throw new \ErrorException("T_IDENTIFIER_DATABASE can only have one part");
        }
        return array(
            "type" => self::T_IDENTIFIER_DATABASE,
            "expr" => "",
            "parts" => $parts,
        );
    }

    public static function get_identifier_backquote_name($expr_or_parts_or_str)
    {
        if (\is_string($expr_or_parts_or_str)) {
            $parts = self::parse_colref($expr_or_parts_or_str);
        } elseif (isset($expr_or_parts_or_str["type"])) {
            if (!self::is_expr_of($expr_or_parts_or_str, self::T_IDENTIFIER)) {
                throw new \ErrorException("not a T_IDENTIFIER");
            }
            if (self::is_expr_of($expr_or_parts_or_str, self::T_IDENTIFIER_ASTERISK)) {
                return "*";
            }
            $parts = $expr_or_parts_or_str["parts"];
        } else {
            $parts = $expr_or_parts_or_str;
        }
        return self::backquote($parts);
    }

    public static function get_one_part_identifier_name($expr_or_parts_or_str)
    {
        if (\is_string($expr_or_parts_or_str)) {
            $parts = self::parse_colref($expr_or_parts_or_str);
        } elseif (isset($expr_or_parts_or_str["type"])) {
            if (!self::is_expr_of($expr_or_parts_or_str, self::T_IDENTIFIER)) {
                throw new \ErrorException("not a T_IDENTIFIER");
            }
            if (self::is_expr_of($expr_or_parts_or_str, self::T_IDENTIFIER_ASTERISK)) {
                return "*";
            }
            $parts = $expr_or_parts_or_str["parts"];
        } else {
            $parts = $expr_or_parts_or_str;
        }
        if (\count($parts) > 1) {
            return false;
        }
        return $parts[0];
    }

    public static function EXP_IDENTIFIER_TABLE($parts_or_str)
    {
        if (\is_string($parts_or_str)) {
            $parts = self::parse_colref($parts_or_str);
        } else {
            $parts = $parts_or_str;
        }
        if (\count($parts) === 0) {
            throw new \ErrorException("T_IDENTIFIER_TABLE cannot be empty");
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
    public static function EXP_TABLE_FUNCTION($name, $sub_tree)
    {
        return array(
            "type" => self::T_TABLE_FUNCTION,
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

    public static function dump_tokens($tokens, $offset = 0, $return = false)
    {
        $s = "";
        foreach (\array_slice($tokens, $offset) as $k => &$token) {
            if (\is_array($token)) {
                $s .= "$k: (" . static::type_name($token[0]) . ") " . \var_export($token[1], true) . "\n";
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
                    $ss[] = ($s === "*" && !$quote) ? "" : $s;
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
                    $ss[] = ($s === "*" && !$quote) ? "" : $s;
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
                $ss[] = $s === "" ? "*" : self::backquote($s);
            }
            return \implode(".", $ss);
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

    protected static function tokens_post_process($tokens, $options = array())
    {
        if (@$options["tokens_post_process_check_error_and_remove_blank"]) {
            $tokens = self::post_process_check_error($tokens);
            $tokens = self::post_process_remove_blank($tokens);
        } elseif (@$options["tokens_post_process_check_error"]) {
            $tokens = self::post_process_check_error($tokens);
        }
        return $tokens;
    }

    protected static function post_process_check_error($tokens)
    {
        $last_token = NULL;
        foreach ($tokens as $token) {
            if (self::is_token_of($token, self::T_CONSTANT) || self::is_token_of($token, self::T_IDENTIFIER)) {
                if ($last_token !== NULL) {
                    throw new \ErrorException("unkown string {$token[1]} after {$last_token[1]}");
                } else {
                    $last_token = $token;
                }
            } elseif (self::is_token_of($token, self::T_INVALID_CHAR)) {
                throw new \ErrorException("unkown char {$token[1]} (Dec:" . ord($token[1]) . ")");
            } else {
                $last_token = NULL;
            }
        }
        return $tokens;
    }

    protected static function post_process_remove_blank($tokens)
    {
        $new_tokens = array();
        foreach ($tokens as $token) {
            if (!self::is_token_of($token, self::T_BLANK)) {
                $new_tokens[] = $token;
            }
        }
        return $new_tokens;
    }
    public static function token_get_all($str, $options = array())
    {
        static $map;
        if ($map === NULL) {
            $map = array(
                "0" => 1, "1" => 1, "2" => 1, "3" => 1, "4" => 1, "5" => 1, "6" => 1, "7" => 1, "8" => 1, "9" => 1,
                "'" => 2, "\"" => 3, "`" => 4, "-" => 5, "/" => 6,
                " " => 7, "\r" => 7, "\n" => 7, "\t" => 7,
            );
            for ($c = 65; $c < 256; $c++) {
                if (($c >= 65 && $c <= 90) || $c === 95 || ($c >= 97 && $c <= 122) || $c >= 127) {
                    $map[\chr($c)] = 9;
                }
            }
        }
        if ($str === "") {
            return array();
        }
        $dont_allow_comment = (bool) @$options["tokens_dont_allow_comment"];
        $tokens = array();
        if ($dont_allow_comment) {
            \preg_match_all("{[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*|\\`(?:[^\\`\\\\]|\\\\.)*\\`|\\\"(?:[^\\\"\\\\]|\\\\.)*\\\"|\\'(?:[^\\'\\\\]|\\\\.)*\\'|\\s+|\\d+(?:\\.\\d*)?(?:[Ee][\\+\\-]?\\d+)?|\\<\\=\\>|\\!\\=|\\>\\=|\\<\\=|\\<\\>|\\<\\<|\\>\\>|\\:\\=|&&|\\|\\||@@|\\-\\>|.}s", $str, $m);
            
            foreach ($m[0] as $token) {
                switch (isset($map[$token[0]]) ? $map[$token[0]] : -1) {
                    case 1:
                        $tokens[] = array(preg_match("{[\\.Ee]}", $token) ? self::T_CONSTANT_DNUMBER : self::T_CONSTANT_LNUMBER, $token);
                        break;
                    case 2:
                        $tokens[] = array(self::T_CONSTANT_STRING, $token);
                        break;
                    case 3:
                        $tokens[] = array(self::T_IDENTIFIER_DOUBLEQUOTE, $token);
                        break;
                    case 4:
                        $tokens[] = array(self::T_IDENTIFIER_BACKQUOTE, $token);
                        break;
                    case 7:
                        $tokens[] = array(self::T_WHITESPACE, $token);
                        break;
                    case 9:
                        if(strcasecmp($token,"NULL")==0){
                            $tokens[] = array(self::T_CONSTANT_NULL,$token);
                        }else{
                            $tokens[] = array(self::T_IDENTIFIER_NOQUOTE, $token);
                        }
                        break;
                    default:
                        $tokens[] = $token;
                }
            }
        } else {
            \preg_match_all("{[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*|\\`(?:[^\\`\\\\]|\\\\.)*\\`|\\\"(?:[^\\\"\\\\]|\\\\.)*\\\"|\\'(?:[^\\'\\\\]|\\\\.)*\\'|\\s+|\\d+(?:\\.\\d*)?(?:[Ee][\\+\\-]?\\d+)?|\\<\\=\\>|\\!\\=|\\>\\=|\\<\\=|\\<\\>|\\<\\<|\\>\\>|\\:\\=|&&|\\|\\||@@|\\-\\>|\\-\\-.*?(?=[\\r\\n]|\$)|\\/\\*.*?(?:\\*\\/|\$)|.}s", $str, $m);
            foreach ($m[0] as $token) {
                switch (isset($map[$token[0]]) ? $map[$token[0]] : -1) {
                    case 1:
                        $tokens[] = array(preg_match("{[\\.Ee]}", $token) ? self::T_CONSTANT_DNUMBER : self::T_CONSTANT_LNUMBER, $token);
                        break;
                    case 2:
                        $tokens[] = array(self::T_CONSTANT_STRING, $token);
                        break;
                    case 3:
                        $tokens[] = array(self::T_IDENTIFIER_DOUBLEQUOTE, $token);
                        break;
                    case 4:
                        $tokens[] = array(self::T_IDENTIFIER_BACKQUOTE, $token);
                        break;
                    case 5:
                        $tokens[] = @$token[1] === "-" ? array(self::T_COMMENT_SINGLE_LINE, $token) : $token;
                        break;
                    case 6:
                        $tokens[] = @$token[1] === "*" ? array(self::T_COMMENT_MULTI_LINE, $token) : $token;
                        break;
                    case 7:
                        $tokens[] = array(self::T_WHITESPACE, $token);
                        break;
                    case 9:
                        if(strcasecmp($token,"NULL")==0){
                            $tokens[] = array(self::T_CONSTANT_NULL,$token);
                        }else{
                            $tokens[] = array(self::T_IDENTIFIER_NOQUOTE, $token);
                        }
                        break;
                    default:
                        $tokens[] = $token;
                }
            }
        }
        $tokens = self::tokens_post_process($tokens, $options);
        return $tokens;
    }

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
        "globalIn" => 8,
        "in" => 8,
        "globalNotIn" => 8,
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
        "globalIn" => " global in ",
        "globalNotIn" => " global not in ",
        "lambda" => "->",
    );

    protected static $keywords_map = array(
        "WITH" => 1, "SELECT" => 1, "FROM" => 1, "FINAL" => 1, "HAVING" => 1,
        "WHERE" => 1, "PREWHERE" => 1, "ORDER" => 1, "LIMIT" => 1, "CROSS" => 1,
        "INNER" => 1, "LEFT" => 1, "RIGHT" => 1, "FULL" => 1, "JOIN" => 1,
        "ON" => 1, "USING" => 1, "ARRAY" => 1, "ALL" => 1, "ANY" => 1, "ASOF" => 1,
        "UNION" => 1, "GLOBAL" => 1, "FORMAT" => 1, "GROUP" => 1, "SETTINGS" => 1
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
    public static function get_clickhouse_case_insensitive_functions()
    {
        //select groupArray(name) from (select name from system.functions where case_insensitive=1 union all select alias_to from system.functions where case_insensitive=1 and alias_to<>'')
        return  array('acos', 'asin', 'tan', 'cos', 'sin', 'log2', 'log', 'atan', 'substring', 'CRC64', 'lower', 'CHAR_LENGTH', 'length', 'ceil', 'floor', 'roundBankers', 'round', 'coalesce', 'trunc', 'dateDiff', 'pow', 'reverse', 'rand', 'now64', 'CRC32', 'CHARACTER_LENGTH', 'FQDN', 'CAST', 'abs', 'char', 'log10', 'now', 'concat', 'CRC32IEEE', 'upper', 'nullIf', 'if', 'ifNull', 'sqrt', 'pi', 'tanh', 'exp', 'position', 'power', 'locate', 'mid', 'replace', 'truncate', 'lcase', 'ucase', 'flatten', 'week', 'ln', 'user', 'yearweek', 'substr', 'ceiling', 'timeSeriesGroupRateSum', 'timeSeriesGroupSum', 'retention', 'sum', 'max', 'min', 'boundingRatio', 'windowFunnel', 'corr', 'count', 'avg', 'STDDEV_POP', 'STDDEV_SAMP', 'COVAR_SAMP', 'VAR_POP', 'BIT_AND', 'VAR_SAMP', 'BIT_XOR', 'COVAR_POP', 'BIT_OR', 'pow', 'position', 'substring', 'replaceAll', 'trunc', 'lower', 'upper', 'arrayFlatten', 'toWeek', 'log', 'currentUser', 'toYearWeek', 'substring', 'ceil', 'stddevPop', 'stddevSamp', 'covarSamp', 'varPop', 'groupBitAnd', 'varSamp', 'groupBitXor', 'covarPop', 'groupBitOr');
    }

    public static function get_clickhouse_aggregate_functions()
    {
        //select groupArray(name) from (select name from system.functions where is_aggregate=1 union all select alias_to from system.functions where is_aggregate=1 and alias_to<>'')
        return  array('aggThrow','groupArrayMovingAvg','entropy','categoricalInformationValue','stochasticLogisticRegression','stochasticLinearRegression','timeSeriesGroupRateSum','timeSeriesGroupSum','retention','maxIntersectionsPosition','groupBitmapXor','groupBitmapOr','groupBitmap','groupBitXor','groupBitOr','groupBitmapAnd','topKWeighted','uniqCombined64','uniqCombined','sumMapFiltered','sumMapFilteredWithOverflow','sumMap','topK','sumKahan','histogram','sum','covarPop','kurtPop','simpleLinearRegression','kurtSamp','skewPop','skewSamp','uniqExact','sumMapWithOverflow','stddevSamp','varSamp','quantilesTiming','covarSampStable','stddevSampStable','covarSamp','quantileTiming','varPopStable','quantilesExactWeighted','max','maxIntersections','uniqHLL12','uniq','min','quantileTDigest','anyHeavy','quantilesTimingWeighted','boundingRatio','anyLast','sequenceMatch','windowFunnel','varSampStable','any','quantilesTDigestWeighted','groupBitAnd','quantileTDigestWeighted','argMax','corr','quantileDeterministic','quantilesTDigest','quantileTimingWeighted','covarPopStable','groupUniqArray','argMin','quantileExactInclusive','quantileExactExclusive','sumWithOverflow','quantiles','sequenceCount','quantileExactWeighted','quantilesDeterministic','quantilesExact','uniqUpTo','quantilesExactInclusive','avgWeighted','quantileExact','groupArrayInsertAt','groupArray','groupArraySample','quantile','varPop','groupArrayMovingSum','quantilesExactExclusive','corrStable','count','stddevPopStable','stddevPop','avg','STDDEV_POP','STDDEV_SAMP','COVAR_SAMP','medianTDigest','VAR_POP','BIT_AND','VAR_SAMP','medianTimingWeighted','BIT_XOR','median','COVAR_POP','medianExactWeighted','medianTDigestWeighted','medianExact','medianTiming','BIT_OR','medianDeterministic','stddevPop','stddevSamp','covarSamp','quantileTDigest','varPop','groupBitAnd','varSamp','quantileTimingWeighted','groupBitXor','quantile','covarPop','quantileExactWeighted','quantileTDigestWeighted','quantileExact','quantileTiming','groupBitOr','quantileDeterministic');
    }

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

    public static function dump_expr($expr, $return = false)
    {
        $func = function ($expr, $walker) {
            $expr = \call_user_func($walker, $expr, true);
            $expr["type"] = static::type_name($expr["type"]);
            return $expr;
        };
        $s = json_encode(self::walker($expr, $func, false), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $s .= "\n";
        $s .= self::create($expr);
        if (!$return) {
            echo $s;
        }
        return $s;
    }

    protected static function walker_impl($expr, $func)
    {
        switch ($expr["type"]) {
            case self::T_TABLE_FUNCTION:
            case self::T_FUNCTION:
            case self::T_SQL_UNION_ALL:
                foreach (["FORMAT_SETTINGS", "sub_tree"] as $key) {
                    if (isset($expr[$key])) {
                        foreach ($expr[$key] as &$sub_expr) {
                            $sub_expr = self::walker($sub_expr, $func, false);
                        }
                    }
                }
                break;
            case self::T_PARAMETRIC_FUNCTION:
                foreach ($expr["sub_tree"] as &$sub_tree) {
                    foreach ($sub_tree as &$sub_expr) {
                        $sub_expr = self::walker($sub_expr, $func, false);
                    }
                }
                break;
            case self::T_SUBEXP:
            case self::T_SUBQUERY:
                $expr["sub_tree"] = self::walker($expr["sub_tree"], $func, false);
                break;
            case self::T_SQL_SELECT:
                foreach (["WITH", "SELECT", "FROM", "ARRAYJOIN", "GROUPBY", "ORDERBY", "SETTINGS", "FORMAT_SETTINGS"] as $key) {
                    if (isset($expr[$key])) {
                        foreach ($expr[$key] as &$sub_expr) {
                            $sub_expr = self::walker($sub_expr, $func, false);
                        }
                    }
                }
                foreach (["PREWHERE", "WHERE", "HAVING"] as $key) {
                    if (isset($expr[$key])) {
                        $expr[$key] = self::walker($expr[$key], $func, false);
                    }
                }
                break;
        }
        foreach (["using"] as $key) {
            if (isset($expr[$key])) {
                foreach ($expr[$key] as &$sub_expr) {
                    $sub_expr = self::walker($sub_expr, $func, false);
                }
            }
        }
        foreach (["on"] as $key) {
            if (isset($expr[$key])) {
                $expr[$key] = self::walker($expr[$key], $func, false);
            }
        }
        return $expr;
    }


    public static function walker($expr, $func, $skip = false)
    {
        if (!$skip) {
            $walker = function ($expr, $skip = false) use ($func) {
                return self::walker($expr, $func, $skip);
            };
            return \call_user_func($func, $expr, $walker);
        }
        return static::walker_impl($expr, $func);
    }

    public static function expr_post_process($expr, $options = array())
    {
        $compact_and = isset($options["expr_post_process_compact_and"]) ? (bool) $options["expr_post_process_compact_and"] : true;
        $compact_or = isset($options["expr_post_process_compact_or"]) ? (bool) $options["expr_post_process_compact_or"] : true;
        $compact_concat = isset($options["expr_post_process_compact_concat"]) ? (bool) $options["expr_post_process_compact_concat"] : true;
        $change_case_insensitive_function_name = isset($options["expr_post_process_change_case_insensitive_function_name"]) ? $options["expr_post_process_change_case_insensitive_function_name"] : true;
        if (!\is_array($change_case_insensitive_function_name)) {
            $name_list = $change_case_insensitive_function_name ? self::get_clickhouse_case_insensitive_functions() : array();
        } else {
            $name_list = $change_case_insensitive_function_name;
        }
        if (!$compact_and && !$compact_or && !$compact_concat && !$name_list) {
            return $expr;
        }
        if ($compact_and) {
            $expr = self::expr_post_process_compact_and($expr);
        }
        if ($compact_or) {
            $expr = self::expr_post_process_compact_or($expr);
        }
        if ($compact_concat) {
            $expr = self::expr_post_process_compact_concat($expr);
        }
        if ($name_list) {
            $expr = self::expr_post_process_change_case_insensitive_function_name($expr, $name_list);
        }
        return $expr;
    }

    public static function expr_post_process_compact_and($expr)
    {
        $func = function ($expr, $walker) {
            $expr = \call_user_func($walker, $expr, true);
            if (ClickHouseSQLParser::is_expr_of_function($expr, "and")) {
                $sub_tree = array();
                foreach ($expr["sub_tree"] as $sub_expr) {
                    if (!@$sub_expr["alias"] && self::is_expr_of_function($sub_expr, "and")) {
                        foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                            if (!self::is_expr_const_true($sub_sub_expr) || @$sub_sub_expr["alias"]) {
                                $sub_tree[] = $sub_sub_expr;
                            }
                        }
                    } else {
                        if (!self::is_expr_const_true($sub_expr) || @$sub_expr["alias"]) {
                            $sub_tree[] = $sub_expr;
                        }
                    }
                }
                if (\count($sub_tree) == 0) {
                    $expr = self::replace_expr($expr, self::$EXP_CONSTANT_1);
                } elseif (\count($sub_tree) == 1) {
                    if (@$sub_tree[0]["alias"]) {
                        $expr = self::replace_expr($expr, self::EXP_SUBEXP($sub_tree[0]));
                    } else {
                        $expr = self::replace_expr($expr, $sub_tree[0]);
                    }
                } else {
                    $expr["sub_tree"] = $sub_tree;
                }
            }
            return $expr;
        };
        $expr = self::walker($expr, $func, false);
        return $expr;
    }

    public static function expr_post_process_compact_or($expr)
    {
        $func = function ($expr, $walker) {
            $expr = \call_user_func($walker, $expr, true);
            if (ClickHouseSQLParser::is_expr_of_function($expr, "or")) {
                $sub_tree = array();
                foreach ($expr["sub_tree"] as $sub_expr) {
                    if (!@$sub_expr["alias"] && self::is_expr_of_function($sub_expr, "or")) {
                        foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                            if (!self::is_expr_const_false($sub_sub_expr) || @$sub_sub_expr["alias"]) {
                                $sub_tree[] = $sub_sub_expr;
                            }
                        }
                    } else {
                        if (!self::is_expr_const_false($sub_expr) || @$sub_expr["alias"]) {
                            $sub_tree[] = $sub_expr;
                        }
                    }
                }
                if (\count($sub_tree) == 0) {
                    $expr = self::replace_expr($expr, self::$EXP_CONSTANT_0);
                } elseif (\count($sub_tree) == 1) {
                    if (@$sub_tree[0]["alias"]) {
                        $expr = self::replace_expr($expr, self::EXP_SUBEXP($sub_tree[0]));
                    } else {
                        $expr = self::replace_expr($expr, $sub_tree[0]);
                    }
                } else {
                    $expr["sub_tree"] = $sub_tree;
                }
            }
            return $expr;
        };
        return self::walker($expr, $func, false);
    }

    public static function expr_post_process_compact_concat($expr)
    {
        $func = function ($expr, $walker) {
            $expr = \call_user_func($walker, $expr, true);
            if (ClickHouseSQLParser::is_expr_of_function($expr, "concat")) {
                $sub_tree = array();
                foreach ($expr["sub_tree"] as $sub_expr) {
                    if (!@$sub_expr["alias"] && self::is_expr_of_function($sub_expr, "concat")) {
                        foreach ($sub_expr["sub_tree"] as $sub_sub_expr) {
                            if (!self::is_expr_const_empty_string($sub_expr) || @$sub_expr["alias"]) {
                                $sub_tree[] = $sub_sub_expr;
                            }
                        }
                    } else {
                        if (!self::is_expr_const_empty_string($sub_expr) || @$sub_expr["alias"]) {
                            $sub_tree[] = $sub_expr;
                        }
                    }
                }
                if (\count($sub_tree) == 0) {
                    $expr = self::replace_expr($expr, self::$EXP_CONSTANT_EMPTY_STRING);
                } elseif (\count($sub_tree) == 1) {
                    if (@$sub_tree[0]["alias"]) {
                        $expr = self::replace_expr($expr, self::EXP_SUBEXP($sub_tree[0]));
                    } else {
                        $expr = self::replace_expr($expr, $sub_tree[0]);
                    }
                } else {
                    $expr["sub_tree"] = $sub_tree;
                }
            }
            return $expr;
        };
        return self::walker($expr, $func, false);
    }

    public static function expr_post_process_change_case_insensitive_function_name($expr, $name_list)
    {
        if (!$name_list) {
            return $expr;
        }
        $case_insensitive_function_name_map = array();
        foreach ($name_list as $func) {
            $case_insensitive_function_name_map[\strtoupper($func)] = $func;
        }
        $func = function ($expr, $walker) use ($case_insensitive_function_name_map) {
            $expr = \call_user_func($walker, $expr, true);
            if (ClickHouseSQLParser::is_expr_of($expr, self::T_FUNCTION)) {
                $upper = \strtoupper($expr["expr"]);
                if (isset($case_insensitive_function_name_map[$upper])) {
                    $expr["expr"] = $case_insensitive_function_name_map[$upper];
                }
            }
            return $expr;
        };
        return self::walker($expr, $func, false);
    }

    protected static function check_and_parse_select($sql, $options)
    {
        if (preg_match("{^[\\s(]*(?:WITH|SELECT)\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_expr($tokens, 0, 0, true);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            if (!self::is_expr_of($expr, self::T_SQL_SELECT_OR_UNION_ALL)) {
                throw new \ErrorException("cannot parse as sql, maybe BUG");
            }
            return $expr;
        }
        return false;
    }

    //expr_post_process_compact_and => default(1)
    //expr_post_process_compact_or => default(1)
    //expr_post_process_compact_concat => default(1)
    //expr_post_process_change_case_insensitive_function_name => default(1)
    protected static function parse_impl($sql, $options = array())
    {
        if ($expr = self::check_and_parse_select($sql, $options)) {
            $expr = self::expr_post_process($expr, $options);
            return $expr;
        } else {
            return self::SQL_ANY($sql);
        }
    }

    public static function parse($sql, $options = array())
    {
        return static::parse_impl($sql, $options);
    }

    //expr_post_process_compact_and => default(1)
    //expr_post_process_compact_or => default(1)
    //expr_post_process_compact_concat => default(1)
    //expr_post_process_change_case_insensitive_function_name => default(1)
    public static function parse_expr($str, $options = array())
    {
        $options["tokens_post_process_check_error_and_remove_blank"] = 1;
        $tokens = self::token_get_all($str, $options);
        list($expr, $index) = self::get_next_expr($tokens, 0, 0);
        if ($index != \count($tokens)) {
            throw new \ErrorException("cannot parse as expr, some token left");
        }
        $expr = self::expr_post_process($expr, $options);
        return $expr;
    }

    protected static function hasAlias($p)
    {
        return isset($p["alias"]) && $p["alias"] !== false && $p["alias"] !== '';
    }

    protected static function aliasStr($p)
    {
        $s = "";
        if (self::hasAlias($p)) {
            if(self::is_expr_of($p,self::T_IDENTIFIER_COLREF) && \count($p["parts"])==1 && $p["parts"][0]===$p["alias"]){
                return $s;
            }
            $s .= " AS " . self::backquote($p["alias"]);
        }
        return $s;
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
            $join = $p["join"];
            $s = "";
            if (@$join["global"]) {
                $s .= " GLOBAL";
            }
            if (@$join["strictness"]) {
                $s .= " " . $join["strictness"];
            }
            if (@$join["type"] !== "INNER") {
                $s .= " " . $join["type"];
            }
            return "$s JOIN ";
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

    protected static function formatStr($p)
    {
        if (@$p["FORMAT"]) {
            $s = " FORMAT " . $p["FORMAT"];
            if (@$p["FORMAT_SETTINGS"]) {
                $s .= " SETTINGS ";
                $k = 0;
                foreach ($p["FORMAT_SETTINGS"] as $key => $expr) {
                    if ($k++ != 0) {
                        $s .= ",";
                    }
                    $s .= "$key=" .  static::create_impl($expr, array())[0];
                }
            }
            return $s;
        }
        return "";
    }

    protected static function exprStr($p, $s)
    {
        return self::joinStr($p) . $s . self::aliasStr($p) . self::orderStr($p) . self::usingStr($p) . self::onStr($p);
    }

    protected static function  create_impl($p, $options)
    {
        switch ($p["type"]) {
            case self::T_CONSTANT_NULL:
                return array(self::exprStr($p, "NULL"), (self::hasAlias($p) ? 0 : 100));
            case self::T_IDENTIFIER_ASTERISK:
                return array(self::exprStr($p, "*"), (self::hasAlias($p) ? 0 : 100));
            case self::T_CONSTANT_LNUMBER:
            case self::T_CONSTANT_DNUMBER:
                return array(self::exprStr($p, $p["expr"]), (self::hasAlias($p) ? 0 : 100));
            case self::T_EXP_ANY:
                return array(self::exprStr($p, "(" . $p["expr"] . ")"), (self::hasAlias($p) ? 0 : 100));
            case self::T_CONSTANT_STRING:
                return array(self::exprStr($p, self::mysql_encode_str($p["expr"])), (self::hasAlias($p) ? 0 : 100));
            case self::T_IDENTIFIER_DATABASE:
            case self::T_IDENTIFIER_COLREF:
            case self::T_IDENTIFIER_TABLE:
                return array(self::exprStr($p, self::backquote($p["parts"])), (self::hasAlias($p) ? 0 : 100));
            case self::T_PARAMETRIC_FUNCTION:
                $s = $p["expr"];
                foreach ($p["sub_tree"] as $sub_tree) {
                    $s .=  static::create_impl(self::EXP_FUNCTION("", $sub_tree), $options)[0];
                }
                return array(self::exprStr($p, $s), (self::hasAlias($p) ? 0 : 100));
            case self::T_TABLE_FUNCTION:
            case self::T_FUNCTION:
                $func = $p["expr"];
                $func_upper = \strtoupper($p["expr"]);
                $sub_tree = @$p["sub_tree"] ? $p["sub_tree"] : array();
                $s = "";
                $map = array("COUNT" => "count");
                if (isset($map[$func_upper])) {
                    $func = $map[$func_upper];
                }
                switch ($func) {
                    case "count":
                        if (\count($sub_tree) === 0) {
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
                            list($str) =  static::create_impl($sub, $options);
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
                        list($str, $sub_precedence) =  static::create_impl($sub_tree[0], $options);
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
                        list($str, $sub_precedence) =  static::create_impl($sub_tree[0], $options);
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
                        list($str, $sub_precedence) =  static::create_impl($sub_tree[0], $options);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        list($str, $sub_precedence) =  static::create_impl($sub_tree[1], $options);
                        if ($sub_precedence <= $precedence) {
                            $s .= "($str)";
                        } else {
                            if ($func === "minus" && $str[0] === "-") {
                                $s .= "($str)"; //  -- is for comment, so add parentheses
                            } else {
                                $s .= "$str";
                            }
                        }
                        break;
                    case "in":
                    case "notIn":
                    case "globalIn":
                    case "globalNotIn":
                        $in_list=$sub_tree[1];
                        if (self::is_expr_of_function($in_list, "tuple") && \count($in_list["sub_tree"])==1 && self::is_expr_of($in_list["sub_tree"][0], self::T_IDENTIFIER_COLREF)) {
                            //clickhouse has a Syntax: 
                            //   If the right side of the operator is the name of a table (for example, UserID IN users), this is equivalent to the subquery UserID IN (SELECT * FROM users). 
                            //   => this makes difficulty to sql rewriter to check table priv
                            //   so rewrite a in (b) to a=b, if b is a table, makes a error => this is what i want
                            $new_expr=self::EXP_FUNCTION(["in"=>"equals","notIn"=>"notEquals","globalIn"=>"equals","globalNotIn"=>"notEquals"][$func],array(
                                $sub_tree[0],
                                $in_list["sub_tree"][0],
                            ));
                            list($s,$precedence)=static::create_impl($new_expr, $options);
                            break;
                        }
                        $precedence = self::$precedence_map[$func];
                        $val = $sub_tree[0];
                        if(self::is_expr_of_function($val, "tuple")){
                            $val["expr"]="";
                        }
                        list($str, $sub_precedence) =  static::create_impl($val, $options);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        $val = $sub_tree[1];
                        if(self::is_expr_of_function($val, "tuple")){
                            $val["expr"]="";
                            foreach ($val["sub_tree"] as &$sub){
                                if(self::is_expr_of_function($sub, "tuple")){
                                    $sub["expr"]="";
                                }
                            }
                        }
                        $s .=  static::create_impl($val, $options)[0];
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
                        list($str, $sub_precedence) =  static::create_impl($val, $options);
                        if ($sub_precedence < $precedence) {
                            $s .= "($str)";
                        } else {
                            $s .= "$str";
                        }
                        $s .= self::$function_to_operator_map[$func];
                        $s .=  static::create_impl($sub_tree[1], $options)[0];
                        break;
                    case "or":
                    case "and":
                        $precedence = self::$precedence_map[$func];
                        $s = "";
                        foreach ($p["sub_tree"] as $k => $sub) {
                            if ($k !== 0) {
                                $s .= self::$function_to_operator_map[$func];
                            }
                            list($str, $sub_precedence) =  static::create_impl($sub, $options);
                            if ($sub_precedence < $precedence) {
                                $s .= "($str)";
                            } else {
                                $s .= "$str";
                            }
                        }
                        break;
                    case "negate":
                        $precedence = self::$precedence_map[$func];
                        list($str, $sub_precedence) =  static::create_impl($sub_tree[0], $options);
                        if ($sub_precedence <= $precedence) {
                            $s .= "-($str)";
                        } else {
                            $s .= "-$str";
                        }
                        break;
                    case "array":
                        $precedence = 100;
                        foreach ($sub_tree as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            list($str) =  static::create_impl($sub, $options);
                            $s .= $str;
                        }
                        $s = "[$s]";
                        break;
                    default:
                        F1: $precedence = 100;
                        foreach ($sub_tree as $k => $sub) {
                            if ($k !== 0) {
                                $s .= ",";
                            }
                            list($str) =  static::create_impl($sub, $options);
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
                return array(self::exprStr($p, $s), $precedence);
            case self::T_SUBEXP:
            case self::T_SUBQUERY:
                return array(self::exprStr($p, "(" .  static::create_impl($p["sub_tree"], $options)[0] . ")"), (self::hasAlias($p) ? 0 : 100));
            case self::T_SQL_ANY:
                return array($p["expr"], 0);
            case self::T_SQL_UNION_ALL:
                $s = "";
                foreach ($p["sub_tree"] as $k => $query) {
                    if ($k != 0) {
                        $s .= " UNION ALL ";
                    }
                    $s .= "(" .  static::create_impl($query, $options)[0] . ")";
                }
                $s .= self::formatStr($p);
                if (@$p["HAS_SEMICOLON"]) {
                    $s .= ";";
                }
                //union all didn't has it's settings
                return array($s, 100);
            case self::T_SQL_SELECT:
                $s = "";
                if (@$p["WITH"]) {
                    $s .= "WITH ";
                    foreach ($p["WITH"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .=  static::create_impl($expr, $options)[0];
                    }
                    $s .= " ";
                }
                $s .= "SELECT";
                if (@$p["OPTION_SELECT_DISTINCT"]) {
                    $s .= " DISTINCT";
                }
                $s .= " ";
                foreach ($p["SELECT"] as $k => $expr) {
                    if ($k != 0) {
                        $s .= ",";
                    }
                    $s .=  static::create_impl($expr, $options)[0];
                }
                if (@$p["FROM"]) {
                    $s .= " FROM ";
                    foreach ($p["FROM"] as $k => $expr) {
                        $s .=  static::create_impl($expr, $options)[0];
                        if ($k == 0) {
                            if (@$p["FINAL"]) {
                                $s .= " FINAL";
                            }
                            if (@$p["ARRAYJOIN"]) {
                                if (@$p["OPTION_ARRAYJOIN_LEFT"]) {
                                    $s .= " LEFT";
                                }
                                $s .= " ARRAY JOIN ";
                                foreach ($p["ARRAYJOIN"] as $k => $expr2) {
                                    if ($k != 0) {
                                        $s .= ",";
                                    }
                                    $s .=  static::create_impl($expr2, $options)[0];
                                }
                                //$s .= " ";
                            }
                        }
                    }
                }
                if (@$p["PREWHERE"]) {
                    $s .= " PREWHERE " .  static::create_impl($p["PREWHERE"], $options)[0];
                }
                if (@$p["WHERE"]) {
                    $s .= " WHERE " .  static::create_impl($p["WHERE"], $options)[0];
                }
                if (@$p["GROUPBY"]) {
                    $s .= " GROUP BY ";
                    foreach ($p["GROUPBY"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .=  static::create_impl($expr, $options)[0];
                    }
                }
                if (@$p["HAVING"]) {
                    $s .= " HAVING " .  static::create_impl($p["HAVING"], $options)[0];
                }
                if (@$p["ORDERBY"]) {
                    $s .= " ORDER BY ";
                    foreach ($p["ORDERBY"] as $k => $expr) {
                        if ($k != 0) {
                            $s .= ",";
                        }
                        $s .=  static::create_impl($expr, $options)[0];
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
                        $s .=  static::create_impl($expr, $options)[0];
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
                        $s .= "$key=" .  static::create_impl($expr, $options)[0];
                    }
                }
                if (@$p["FORMAT"]) {
                    $s .= " FORMAT " . $p["FORMAT"];
                    if (@$p["FORMAT_SETTINGS"]) {
                        $s .= " SETTINGS ";
                        $k = 0;
                        foreach ($p["FORMAT_SETTINGS"] as $key => $expr) {
                            if ($k++ != 0) {
                                $s .= ",";
                            }
                            $s .= "$key=" .  static::create_impl($expr, $options)[0];
                        }
                    }
                }
                if (@$p["HAS_SEMICOLON"]) {
                    $s .= ";";
                }
                return array($s, 0);
            default:
                throw new \ErrorException("BUG");
        }
    }

    public static function create($p, $options = array())
    {
        return static::create_impl($p, $options)[0];
    }


    protected static function get_next_expr($tokens, $index, $precedence, $allow_sql = false)
    {
        $token = @$tokens[$index];
        if($token===NULL){
            throw new \ErrorException("no more token");
        }
        $is_sql = false;
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
                            if (!$allow_sql) {
                                throw new \ErrorException("sql must in parentheses");
                            }
                            list($val1, $index) = self::get_next_select($tokens, $index);
                            $is_sql = true;
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
            if ($is_sql && !isset([")" => 1, ";" => 1, "UNION" => 1, "FORMAT" => 1][$operator])) {
                throw new \ErrorException("unexpect token " . self::token_to_string($token));
            }
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
                    } elseif (self::is_token_of($token, "*")) {
                        if (!self::is_expr_of($val1, self::T_IDENTIFIER_COLREF)) {
                            throw new \ErrorException("BUG");
                        }
                        $val1["parts"][] = "";
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
                    $sub_allow_sql = false;
                    if (self::is_expr_of($val1, self::T_IDENTIFIER_COLREF)) {
                        if (\count($val1["parts"]) != 1) {
                            throw new \ErrorException("expect FUNCTION NAME got " . self::create($val1));
                        }
                        $to_upper_func_name = \strtoupper($val1["parts"][0]);
                        $sub_allow_sql = $to_upper_func_name === "";
                    } elseif (self::is_expr_of($val1, self::T_FUNCTION)) {
                        if ($val1["expr"] === "") {
                            throw new \ErrorException("BUG");
                        }
                        if ($val1["expr"] === "tuple") {
                            throw new \ErrorException("tuple is not a PARAMETRIC_FUNCTION ");
                        }
                        $val1["type"] = self::T_PARAMETRIC_FUNCTION;
                        $val1["sub_tree"] = array($val1["sub_tree"]);
                    } elseif (self::is_expr_of($val1, self::T_PARAMETRIC_FUNCTION)) {
                    } else {
                        throw new \ErrorException("expect FUNCTION NAME got " . self::create($val1));
                    }
                    if ($token !== ")") {
                        switch ($to_upper_func_name) {
                            case "COUNT":
                                if (self::is_token_of($token, "DISTINCT")) {
                                    $token = @$tokens[++$index];
                                    $val1["parts"][0] = "countDistinct";
                                } elseif ($token === "*") {
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
                            list($expr, $index) = self::get_next_expr($tokens, $index, 0, $sub_allow_sql);
                            $token = @$tokens[$index];
                            if ($sub_allow_sql && self::is_expr_of($expr, self::T_SQL_SELECT_OR_UNION_ALL)) {
                                $index++;
                                if ($token !== ")") {
                                    throw new \ErrorException("sql must in parentheses");
                                }
                                if (@$expr["FORMAT"]) {
                                    throw new \ErrorException("the <SUBQUERY> cannot has FORMAT");
                                }
                                if (@$expr["HAS_SEMICOLON"]) {
                                    throw new \ErrorException("the <SUBQUERY> cannot has SEMICOLON");
                                }
                                $val1 = self::EXP_SUBQUERY($expr);
                                continue 3;
                            }
                            $sub_allow_sql = false;
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
                        F4: $index++;
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
                case "GLOBAL":
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "IN")) {
                        $operator = "globalIn";
                        goto F3;
                    } elseif (self::is_token_of($token, "NOT")) {
                        $token = @$tokens[++$index];
                        if (self::is_token_of($token, "IN")) {
                            $operator = "globalNotIn";
                            goto F3;
                        }
                    }
                    return array($val1, $old_index);;
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
                case "%":
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
                    $sub_allow_sql = true;
                    for (;;) {
                        list($expr, $index) = self::get_next_expr($tokens, $index, 0, $sub_allow_sql);
                        $token = @$tokens[$index];
                        if ($sub_allow_sql && self::is_expr_of($expr, self::T_SQL_SELECT_OR_UNION_ALL)) {
                            if ($token !== ")") {
                                throw new \ErrorException("sql must in parentheses");
                            }
                            $index++;
                            if (@$expr["FORMAT"]) {
                                throw new \ErrorException("the <SUBQUERY> cannot has FORMAT");
                            }
                            if (@$expr["HAS_SEMICOLON"]) {
                                throw new \ErrorException("the <SUBQUERY> cannot has SEMICOLON");
                            }
                            $val1 = self::EXP_FUNCTION($operator, array($val1, self::EXP_SUBQUERY($expr)));
                            continue 3;
                        }
                        $sub_allow_sql = false;
                        $sub_tree[] = $expr;
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
                case ";":
                    if (!$is_sql) {
                        return array($val1, $index);
                    }
                    $unionPrecedence = 1;
                    if ($unionPrecedence <= $precedence) {
                        return array($val1, $old_index);
                    }
                    if (self::is_expr_of($val1, self::T_SUBQUERY)) {
                        if (self::hasAlias($val1)) {
                            throw new \ErrorException("the (QUERY) used in UNION ALL cannot has alias");
                        }
                        $val1 = $val1["sub_tree"];
                    }
                    for (; self::is_token_of($token, ";"); $token = @$tokens[++$index]) {
                    }
                    $val1["HAS_SEMICOLON"] = 1;
                    $is_sql = true;
                    continue 2;
                case "FORMAT":
                    if (!$is_sql) {
                        return array($val1, $index);
                    }
                    $unionPrecedence = 1;
                    if ($unionPrecedence <= $precedence) {
                        return array($val1, $old_index);
                    }
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, self::T_IDENTIFIER_NOQUOTE)) {
                        throw new \ErrorException("expect (FORMAT TYPE) got " . self::token_to_string($token));
                    }
                    if (self::is_expr_of($val1, self::T_SUBQUERY)) {
                        if (self::hasAlias($val1)) {
                            throw new \ErrorException("the (QUERY) used in FORMAT cannot has alias");
                        }
                        $val1 = $val1["sub_tree"];
                    }
                    if (isset($val1["HAS_SEMICOLON"])) {
                        throw new \ErrorException("the query already has semicolon");
                    }
                    if (isset($val1["FORMAT"])) {
                        throw new \ErrorException("the query already has format");
                    }
                    $val1["FORMAT"] = $token[1];
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "SETTINGS")) {
                        $val1["FORMAT_SETTINGS"] = array();
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
                            $val1["FORMAT_SETTINGS"][$key] = self::token_to_constant_expr($token);
                            $token = @$tokens[++$index];
                            if ($token === ",") {
                                $index++;
                            } else {
                                break;
                            }
                        }
                    }
                    $is_sql = true;
                    continue 2;
                case "UNION":
                    if (!$is_sql) {
                        return array($val1, $index);
                    }
                    $unionPrecedence = 1;
                    if ($unionPrecedence <= $precedence) {
                        return array($val1, $old_index);
                    }
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "ALL")) {
                        throw new \ErrorException("expect 'ALL' after 'UNION' got " . self::token_to_string($token));
                    }
                    if (self::is_expr_of($val1, self::T_SUBQUERY)) {
                        if (self::hasAlias($val1)) {
                            throw new \ErrorException("the (QUERY) used in UNION ALL cannot has alias");
                        }
                        $val1 = $val1["sub_tree"];
                    }
                    if (isset($val1["FORMAT"])) {
                        throw new \ErrorException("the (QUERY) used in UNION ALL cannot has format");
                    }
                    if (isset($val1["HAS_SEMICOLON"])) {
                        throw new \ErrorException("the (QUERY) used in UNION ALL cannot has semicolon");
                    }
                    if (self::is_expr_of($val1, self::T_SQL_SELECT)) {
                        $val1 = self::SQL_UNION_ALL([$val1]);
                    }
                    if (!self::is_expr_of($val1, self::T_SQL_UNION_ALL)) {
                        throw new \ErrorException("expect (QUERY) in 'UNION ALL' ");
                    }
                    list($val2, $index) = self::get_next_expr($tokens, $index + 1, $unionPrecedence, true);
                    if (self::is_expr_of($val2, self::T_SUBQUERY)) {
                        if (self::hasAlias($val2)) {
                            throw new \ErrorException("the (QUERY) used in UNION ALL cannot has alias");
                        }
                        $val2 = $val2["sub_tree"];
                    }
                    if (isset($val2["FORMAT"])) {
                        throw new \ErrorException("the (QUERY) used in UNION ALL cannot has format");
                    }
                    if (isset($val2["HAS_SEMICOLON"])) {
                        throw new \ErrorException("the (QUERY) used in UNION ALL cannot has semicolon");
                    }
                    if (self::is_expr_of($val2, self::T_SQL_SELECT)) {
                        $val2 = self::SQL_UNION_ALL([$val2]);
                    }
                    if (!self::is_expr_of($val2, self::T_SQL_UNION_ALL)) {
                        throw new \ErrorException("expect (QUERY) in 'UNION ALL' ");
                    }
                    foreach ($val2["sub_tree"] as $q) {
                        $val1["sub_tree"][] = $q;
                    }
                    $is_sql = true;
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
            $obj["OPTION_SELECT_DISTINCT"] = 1;
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
            }elseif(self::is_expr_of($expr, self::T_FUNCTION)){
                $expr["type"] = self::T_TABLE_FUNCTION;
            }
            $token = @$tokens[$index];
            if (!isset($expr["alias"]) && self::is_token_of($token, self::T_IDENTIFIER) && !self::is_token_keyword($token)) {
                $expr["alias"] = self::parse_colref($token[1])[0];
                $index++;
                $token = @$tokens[$index];
            }
            if (self::is_token_of($token, "FINAL")) {
                $token = @$tokens[++$index];
                $obj["FINAL"] = 1;
            }
            $obj["FROM"][] = $expr;
            $token = @$tokens[$index];
            if (self::is_token_of($token, "LEFT") && self::is_token_of(@$tokens[$index + 1], "ARRAY")) {
                $token = @$tokens[++$index];
                $obj["OPTION_ARRAYJOIN_LEFT"] = 1;
            }
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
                $join = array();
                if (self::is_token_of($token, ",")) {
                    $join["type"] = "CROSS";
                    goto F5;
                }
                if (self::is_token_of($token, "GLOBAL")) {
                    $token = @$tokens[++$index];
                    $join["global"] = 1;
                }
                if (self::is_token_of($token, "ALL")) {
                    $token = @$tokens[++$index];
                    //$join["strictness"] = "ALL";
                } elseif (self::is_token_of($token, "ANY")) {
                    $token = @$tokens[++$index];
                    $join["strictness"] = "ANY";
                } elseif (self::is_token_of($token, "ASOF")) {
                    $token = @$tokens[++$index];
                    $join["strictness"] = "ASOF";
                } elseif (self::is_token_of($token, "CROSS")) {
                    $join["type"] = "CROSS";
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after 'CROSS' got " . self::token_to_string($token));
                    }
                    $token = @$tokens[++$index];
                    goto F5;
                }
                if (self::is_token_of($token, "INNER")) {
                    $token = @$tokens[++$index];
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after 'INNER' got " . self::token_to_string($token));
                    }
                }
                if (self::is_token_of($token, "JOIN")) {
                    $join["type"] = "INNER";
                    $token = @$tokens[++$index];
                } else {
                    if (self::is_token_of($token, "LEFT")) {
                        $join["type"] = "LEFT";
                    } elseif (self::is_token_of($token, "RIGHT")) {
                        $join["type"] = "RIGHT";
                    } elseif (self::is_token_of($token, "FULL")) {
                        $join["type"] = "FULL";
                    } else {
                        if ($join) {
                            throw new \ErrorException("expect 'INNER' 'LEFT' 'RIGHT' 'FULL' 'JOIN' got " . self::token_to_string($token));
                        }
                        break;
                    }
                    $token = @$tokens[++$index];
                    if (self::is_token_of($token, "OUTER")) {
                        $token = @$tokens[++$index];
                    }
                    if (!self::is_token_of($token, "JOIN")) {
                        throw new \ErrorException("expect 'JOIN' after '{$join["type"]}' got " . self::token_to_string($token));
                    }
                    $token = @$tokens[++$index];
                }
                
                F5:
                list($expr, $index) = self::get_next_expr($tokens, $index, 0);
                // if(@$join["strictness"]==="ALL"){
                //     unset($join["strictness"]);
                // }
                $expr["join"] = $join;
                if (self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                    $expr["type"] = self::T_IDENTIFIER_TABLE;
                }elseif(self::is_expr_of($expr, self::T_FUNCTION)){
                    $expr["type"] = self::T_TABLE_FUNCTION;
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
                if ($join["type"] === "CROSS") {
                    $obj["FROM"][] = $expr;
                    continue;
                }
                if (self::is_token_of($token, "USING")) {
                    $expr["using"] = array();
                    $index++;
                    $token = @$tokens[$index];
                    for ($quote_cnt = 0; ;) {
                        if (!self::is_token_of($token, "(")) {
                            break;
                        }
                        $index++;
                        $token = @$tokens[$index];
                        $quote_cnt++;
                    }
                    for (; ;) {
                        list($expr2, $index) = self::get_next_expr($tokens, $index, 0, false);
                        $expr["using"][] = $expr2;
                        $token = @$tokens[$index];
                        if ($token === ",") {
                            $index++;
                        } else {
                            break;
                        }
                    }
                    for (; $quote_cnt > 0;) {
                        if (!self::is_token_of($token, ")")) {
                            throw new ClickHouseSQLParserParseError("expect ')' got " . self::token_to_string($token));
                        }
                        $index++;
                        $token = @$tokens[$index];
                        $quote_cnt--;
                    }
                } elseif (self::is_token_of($token, "ON")) {
                    $index++;
                    $token = @$tokens[$index];
                    for ($quote_cnt = 0; ;) {
                        if (!self::is_token_of($token, "(")) {
                            break;
                        }
                        $index++;
                        $token = @$tokens[$index];
                        $quote_cnt++;
                    }
                    list($expr2, $index) = self::get_next_expr($tokens, $index, 0, false);
                    $token = @$tokens[$index];
                    $expr["on"] = $expr2;
                    for (; $quote_cnt > 0;) {
                        if (!self::is_token_of($token, ")")) {
                            throw new ClickHouseSQLParserParseError("expect ')' got " . self::token_to_string($token));
                        }
                        $index++;
                        $token = @$tokens[$index];
                        $quote_cnt--;
                    }
                } else {
                    throw new \ErrorException("expect 'USING' or 'ON' after '{$join["type"]}' got " . self::token_to_string($token));
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
            $obj["LIMIT"] = array(
                "offset" => $offset, "row_count" => $row_count
            );
        }
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

    protected static function get_next_semicolon(&$obj, $tokens, $index)
    {
        $token = @$tokens[$index];
        if (self::is_token_of($token, ";")) {
            for (; self::is_token_of($token, ";"); $token = @$tokens[++$index]) {
            }
            $obj["HAS_SEMICOLON"] = 1;
        }
        return array($index);
    }


    protected static function get_next_format(&$obj, $tokens, $index)
    {
        $token = @$tokens[$index];
        if (self::is_token_of($token, "FORMAT")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, self::T_IDENTIFIER_NOQUOTE)) {
                throw new \ErrorException("expect (FORMAT TYPE) got " . self::token_to_string($token));
            }
            $obj["FORMAT"] = $token[1];
            $token = @$tokens[++$index];
            if (self::is_token_of($token, "SETTINGS")) {
                $obj["FORMAT_SETTINGS"] = array();
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
                    $val1["FORMAT_SETTINGS"][$key] = self::token_to_constant_expr($token);
                    $token = @$tokens[++$index];
                    if ($token === ",") {
                        $index++;
                    } else {
                        break;
                    }
                }
            }
        }
        if (self::is_token_of($token, ";")) {
            for (; self::is_token_of($token, ";"); $token = @$tokens[++$index]) {
            }
            $obj["HAS_SEMICOLON"] = 1;
        }
        return array($index);
    }

    public static function query_set_format(&$query_expr, $format)
    {
        unset($query_expr["FORMAT"]);
        unset($query_expr["FORMAT_SETTINGS"]);
        if (!isset($format["FORMAT"])) {
            return;
        }
        $query_expr["FORMAT"] = $format["FORMAT"];
        if (isset($format["FORMAT_SETTINGS"])) {
            $query_expr["FORMAT_SETTINGS"] = $format["FORMAT_SETTINGS"];
        }
    }

    public static function query_remove_format(&$query_expr, $default = NULL)
    {
        if (ClickHouseSQLParser::is_expr_of($query_expr, ClickHouseSQLParser::T_SQL_ANY)) {
            throw new \ErrorException("BUG");
        }
        $format = array();
        if (isset($query_expr["FORMAT"])) {
            $format["FORMAT"] = $query_expr["FORMAT"];
            unset($query_expr["FORMAT"]);
        } elseif ($default !== NULL) {
            unset($query_expr["FORMAT_SETTINGS"]);
            $format["FORMAT"] = $default;
            return $format;
        } else {
            unset($query_expr["FORMAT_SETTINGS"]);
            return $format;
        }
        if (isset($query_expr["FORMAT_SETTINGS"])) {
            $format["FORMAT_SETTINGS"] = $query_expr["FORMAT_SETTINGS"];
            unset($query_expr["FORMAT_SETTINGS"]);
        }
        return $format;
    }
}
