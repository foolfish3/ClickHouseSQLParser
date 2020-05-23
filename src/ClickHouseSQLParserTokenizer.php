<?php

namespace ClickHouseSQLParser;

//require_once __DIR__ . "/ClickHouseSQLParserTypes.php";
class ClickHouseSQLParserTokenizer extends ClickHouseSQLParserTypes
{
    protected function __construct()
    {
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
            preg_match_all("{[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*|\\`(?:[^\\`\\\\]|\\\\.)*\\`|\\\"(?:[^\\\"\\\\]|\\\\.)*\\\"|\\'(?:[^\\'\\\\]|\\\\.)*\\'|\\s+|\\d+(?:\\.\\d*)?(?:[Ee][\\+\\-]?\\d+)?|\\<\\=\\>|\\!\\=|\\>\\=|\\<\\=|\\<\\>|\\<\\<|\\>\\>|\\:\\=|&&|\\|\\||@@|\\-\\>|.}s", $str, $m);
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
                        $tokens[] = array(self::T_IDENTIFIER_NOQUOTE, $token);
                        break;
                    default:
                        $tokens[] = $token;
                }
            }
        } else {
            preg_match_all("{[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*|\\`(?:[^\\`\\\\]|\\\\.)*\\`|\\\"(?:[^\\\"\\\\]|\\\\.)*\\\"|\\'(?:[^\\'\\\\]|\\\\.)*\\'|\\s+|\\d+(?:\\.\\d*)?(?:[Ee][\\+\\-]?\\d+)?|\\<\\=\\>|\\!\\=|\\>\\=|\\<\\=|\\<\\>|\\<\\<|\\>\\>|\\:\\=|&&|\\|\\||@@|\\-\\>|\\-\\-.*?(?=[\\r\\n]|\$)|\\/\\*.*?(?:\\*\\/|\$)|.}s", $str, $m);
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
                        $tokens[] = array(self::T_IDENTIFIER_NOQUOTE, $token);
                        break;
                    default:
                        $tokens[] = $token;
                }
            }
        }
        $tokens = self::tokens_post_process($tokens, $options);
        return $tokens;
    }
}
