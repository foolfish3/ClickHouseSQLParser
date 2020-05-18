<?php

namespace ClickHouseSQLParser;

require_once __DIR__ . "/ClickHouseSQLParserTypes.php";

class ClickHouseSQLParserTokenizer extends ClickHouseSQLParserTypes
{
    protected function __construct()
    {
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
        return ($c >= 48 && $c <= 57) || ($c >= 65 && $c <= 90) || $c === 95 || ($c >= 97 && $c <= 122) || $c >= 127;
    }

    protected static function is_valid_name_start_char($char)
    {
        $c = ord($char);
        return ($c >= 65 && $c <= 90) || $c === 95 || ($c >= 97 && $c <= 122) || $c >= 127;
    }

    protected static function get_splitters_map()
    {
        static $splitters_map;
        if ($splitters_map === NULL) {
            $splitters = array(
                "<=>",
                "\r\n", "!=", ">=", "<=", "<>", "<<", ">>", ":=", "&&", "||", "@@", "->",
                "[", "]", "{", "}",
                ">", "<", "!", "^", "&", "|", "=", "(", ")", "\t", "\r", "\n", " ", "@", ":", "+", "-", "*", "/", "%", ";", ",", ".", ":", "?", "#", "~", "$",
                "\"", "'", "`", "\\",
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
        if (\strcasecmp("NULL", $s) === 0) {
            return array(array(self::T_CONSTANT_NULL, $s), $index);
        }
        return array(array(self::T_IDENTIFIER_NOQUOTE, $s), $index);
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
                    "\"" => self::T_IDENTIFIER_DOUBLEQUOTE,
                    "'" => self::T_CONSTANT_STRING,
                    "`" => self::T_IDENTIFIER_BACKQUOTE,
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

    protected static function get_next_token($str, $index, $dont_allow_comment)
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
        $code = isset($map[$c]) ? $map[$c] : -1;
        switch ($code) {
            case 1:
                list($token, $index) = self::get_next_quote($str, $index);
                break;
            case 2:
                if (!$dont_allow_comment) {
                    list($token, $index) = self::get_next_comment($str, $index);
                } else {
                    $token = false;
                }
                break;
            case 4:
                list($token, $index) = self::get_next_number($str, $index);
                break;
            case 5:
                list($token, $index) = self::get_next_whitespace($str, $index);
                break;
            case 6:
                return array(false, $index); //END
            case -1:
                break;
            default:
                throw new \ErrorException("BUG");
        }
        if ($code === -1 || $token === false) {
            if (self::is_valid_name_start_char($c)) {
                return self::get_next_string($str, $index);
            } else {
                if (($r = self::get_next_splitter("", $str, $index, self::get_splitters_map()))) {
                    return $r;
                } else {
                    return array(array(self::T_INVALID_CHAR, $c), $index + 1);
                    //throw new \ErrorException("unkown char $c (" . ord($c) . ")");
                }
            }
        }
        return array($token, $index);
    }

    protected static function get_next_number($str, $index)
    {
        static $map = array(
            "0" => 0, "1" => 1, "2" => 2, "3" => 3, "4" => 4, "5" => 5, "6" => 6, "7" => 7, "8" => 8, "9" => 9,
            //"A" => 10, "a" => 10, "B" => 11, "b" => 11, "C" => 12, "c" => 12, "D" => 13, "d" => 13, "E" => 14, "e" => 14, "F" => 15, "f" => 15,
            "" => 16, "." => 17,
            //"X" => 18, "x" => 18,
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

    //tokens_post_process_check_error_and_remove_blank => default(0)
    //tokens_post_process_check_error  => default(0)
    //tokens_dont_allow_comment => default(0)
    public static function token_get_all($str, $options = array())
    {
        if ($str === "") {
            return array();
        }
        $tokens = array();
        $index = 0;
        $dont_allow_comment = @$options["tokens_dont_allow_comment"] ? true : false;
        for (;;) {
            list($token, $index) = self::get_next_token($str, $index, $dont_allow_comment);
            if ($token === false) {
                break;
            } else {
                $tokens[] = $token;
            }
        }
        $tokens = self::tokens_post_process($tokens, $options);
        return $tokens;
    }
}
