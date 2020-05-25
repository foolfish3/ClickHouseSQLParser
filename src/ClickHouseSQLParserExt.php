<?php

namespace ClickHouseSQLParser;

class ClickHouseSQLParserExt extends ClickHouseSQLParser
{
    protected function __construct()
    {
    }
    const T_SQL_SHOW_DATABASES   = 9201;
    const T_SQL_SHOW_TABLES      = 9202;
    const T_SQL_SHOW_PROCESSLIST = 9203;
    const T_SQL_SHOW_CREATE_TABLE = 9204;
    const T_SQL_CREATE_DATABASE  = 9310;
    const T_SQL_CREATE_TEMPORARY_TABLE_AS = 9320;
    const T_SQL_DROP_DATABASE = 9410;
    const T_SQL_DROP_TABLE    = 9420;
    const T_SQL_DESC_TABLE = 9810;
    const T_SQL_TRUNCATE_TABLE = 9820;
    const T_SQL_USE = 9830;

    //step 1
    public static function type_name($code)
    {
        $map = array(
            self::T_SQL_SHOW_DATABASES => "T_SQL_SHOW_DATABASES",
            self::T_SQL_SHOW_TABLES => "T_SQL_SHOW_TABLES",
            self::T_SQL_SHOW_PROCESSLIST => "T_SQL_SHOW_PROCESSLIST",
            self::T_SQL_SHOW_CREATE_TABLE => "T_SQL_SHOW_CREATE_TABLE",
            self::T_SQL_CREATE_DATABASE => "T_SQL_CREATE_DATABASE",
            self::T_SQL_CREATE_TEMPORARY_TABLE_AS => "T_SQL_CREATE_TEMPORARY_TABLE_AS",
            self::T_SQL_DROP_DATABASE => "T_SQL_DROP_DATABASE",
            self::T_SQL_DROP_TABLE => "T_SQL_DROP_TABLE",
            self::T_SQL_DESC_TABLE => "T_SQL_DESC_TABLE",
            self::T_SQL_TRUNCATE_TABLE => "T_SQL_TRUNCATE_TABLE",
            self::T_SQL_USE => "T_SQL_USE",
        );
        if (isset($map[$code])) {
            return $map[$code];
        } else {
            return parent::type_name($code);
        }
    }

    //step 2
    protected static function parse_impl($sql, $options = array())
    {
        if ($expr = self::check_and_parse_select($sql, $options)) {
            return $expr;
        } elseif (preg_match("{^\\s*SHOW\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_show($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*DESC\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_desc($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*DROP\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_drop($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*TRUNCATE\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_truncate($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*CREATE\\s+DATABASE\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_create_database($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*USE\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_use($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } elseif (preg_match("{^\\s*CREATE\\s+TEMPORARY\\s+TABLE\\s}si", $sql)) {
            $options["tokens_post_process_check_error_and_remove_blank"] = 1;
            $tokens = self::token_get_all($sql, $options);
            list($expr, $index) = self::get_next_create_temporary_table_as($tokens, 0);
            if ($index != \count($tokens)) {
                throw new \ErrorException("cannot parse as sql, some token left");
            }
            return $expr;
        } else {
            return self::SQL_ANY($sql);
        }
    }

    //step 3
    protected static function walker_impl($expr, $func)
    {
        switch ($expr["type"]) {
            case self::T_SQL_DESC_TABLE:
            case self::T_SQL_SHOW_CREATE_TABLE:
            case self::T_SQL_TRUNCATE_TABLE:
            case self::T_SQL_DROP_TABLE:
                foreach (["table"] as $key) {
                    if (isset($expr[$key])) {
                        $expr[$key] = self::walker($expr[$key], $func, false);
                    }
                }
                break;
            case self::T_SQL_USE:
            case self::T_SQL_SHOW_TABLES:
            case self::T_SQL_CREATE_DATABASE:
            case self::T_SQL_DROP_DATABASE:
                foreach (["database"] as $key) {
                    if (isset($expr[$key])) {
                        $expr[$key] = self::walker($expr[$key], $func, false);
                    }
                }
                break;
            case self::T_SQL_CREATE_TEMPORARY_TABLE_AS:
                foreach (["table", "sub_tree"] as $key) {
                    if (isset($expr[$key])) {
                        $expr[$key] = self::walker($expr[$key], $func, false);
                    }
                }
                break;
            case self::T_SQL_SHOW_PROCESSLIST:
            case self::T_SQL_SHOW_DATABASES:
                break;
        }
        return parent::walker_impl($expr, $func);
    }

    //step 4
    protected static function create_impl($p, $options)
    {
        switch ($p["type"]) {
            case self::T_SQL_SHOW_DATABASES:
                return array("SHOW DATABASES", 100);
            case self::T_SQL_SHOW_TABLES:
                return array("SHOW TABLES" . (@$p["database"] ? " FROM " . static::create_impl($p["database"], $options)[0] : "") . (@$p["LIKE"] ? " LIKE " . self::mysql_encode_str($p["LIKE"]) : ""), 100);
            case self::T_SQL_SHOW_PROCESSLIST:
                return array("SHOW PROCESSLIST", 100);
            case self::T_SQL_SHOW_CREATE_TABLE:
                return array("SHOW CREATE " . (@$p["TEMPORARY"] ? "TEMPORARY " : "") . "TABLE " . static::create_impl($p["table"], $options)[0], 100);
            case self::T_SQL_DESC_TABLE:
                return array("DESC " . static::create_impl($p["table"], $options)[0], 100);
            case self::T_SQL_DROP_DATABASE:
                return array("DROP DATABASE " . (@$p["IFEXISTS"] ? "IF EXISTS " : "") . static::create_impl($p["database"], $options)[0], 100);
            case self::T_SQL_USE:
                return array("USE " . static::create_impl($p["database"], $options)[0], 100);
            case self::T_SQL_DROP_TABLE:
                return array("DROP TABLE " . (@$p["IFEXISTS"] ? "IF EXISTS " : "") . static::create_impl($p["table"], $options)[0], 100);
            case self::T_SQL_TRUNCATE_TABLE:
                return array("TRUNCATE TABLE " . (@$p["IFEXISTS"] ? "IF EXISTS " : "") . static::create_impl($p["table"], $options)[0], 100);
            case self::T_SQL_CREATE_DATABASE:
                return array("CREATE DATABASE " . (@$p["IFNOTEXISTS"] ? "IF NOT EXISTS " : "") . static::create_impl($p["database"], $options)[0], 100);
            case self::T_SQL_CREATE_TEMPORARY_TABLE_AS:
                return array("CREATE TEMPORARY TABLE " . (@$p["IFNOTEXISTS"] ? "IF NOT EXISTS " : "") . static::create_impl($p["table"], $options)[0] . " AS " . static::create_impl($p["sub_tree"], $options)[0], 100);
        }
        return parent::create_impl($p, $options);
    }

    protected static function get_next_create_temporary_table_as($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_CREATE_TEMPORARY_TABLE_AS;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "CREATE")) {
            throw new \ErrorException("expect 'CREATE' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (!self::is_token_of($token, "TEMPORARY")) {
            throw new \ErrorException("expect 'TEMPORARY' after 'CREATE' got " . self::token_to_string($token));
        }

        $token = @$tokens[++$index];
        if (!self::is_token_of($token, "TABLE")) {
            throw new \ErrorException("expect 'TABLE' after 'CREATE TEMPORARY ...' got " . self::token_to_string($token));
        }

        $token = @$tokens[++$index];
        if (self::is_token_of($token, "IF")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "NOT")) {
                throw new \ErrorException("expect 'NOT' after 'CREATE TEMPORARY TABLE IF ...' got " . self::token_to_string($token));
            }
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "EXISTS")) {
                throw new \ErrorException("expect 'EXISTS' after 'CREATE TEMPORARY TABLE IF NOT ...' got " . self::token_to_string($token));
            }
            $token = @$tokens[++$index];
            $obj["IFNOTEXISTS"] = 1;
        }

        list($expr, $index) = self::get_next_expr($tokens, $index, 100);
        if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("expect <TABLE NAME> after 'CREATE TEMPORARY TABLE [IF NOT EXISTS] ...' got " . self::create($expr));
        }
        $expr["type"] = self::T_IDENTIFIER_TABLE;
        $obj["table"] = $expr;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "AS")) {
            throw new \ErrorException("expect 'AS' after 'CREATE TEMPORARY TABLE [IF NOT EXISTS] <TABLE NAME> ...' got " . self::token_to_string($token));
        }

        list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0, true);
        if ($index != \count($tokens)) {
            throw new \ErrorException("cannot parse as sql, some token left");
        }
        if (self::hasAlias($expr)) {
            throw new \ErrorException("the <QUERY|TABLE> used in 'CREATE TEMPORARY TABLE [IF NOT EXISTS] AS <QUERY|TABLE>' cannot has alias");
        }
        if (self::is_expr_of($expr, self::T_SUBQUERY)) {
            if (@$expr["FORMAT"]) {
                throw new \ErrorException("the <QUERY> used in 'CREATE TEMPORARY TABLE [IF NOT EXISTS] AS <QUERY|TABLE>' cannot has FORMAT");
            }
            $expr = $expr["sub_tree"];
        }
        if (!self::is_expr_of($expr, self::T_SQL_ALLOW_IN_SUBQUERY) && !self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("cannot parse as sql, maybe BUG");
        }
        if (@$expr["HAS_SEMICOLON"]) {
            unset($expr["HAS_SEMICOLON"]);
            $obj["HAS_SEMICOLON"] = 1;
        }
        $obj["sub_tree"] = $expr;
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_use($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_USE;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "USE")) {
            throw new \ErrorException("expect 'USE' got " . self::token_to_string($token));
        }
        list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
        if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("expect <DATABASE NAME> after 'USE ...' got " . self::create($expr));
        }
        if (\count($expr["parts"]) !== 1) {
            throw new \ErrorException("<DATABASE NAME> can only have one part got " . self::create($expr));
        }
        $expr["type"] = self::T_IDENTIFIER_DATABASE;
        $obj["database"] = $expr;
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_create_database($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_CREATE_DATABASE;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "CREATE")) {
            throw new \ErrorException("expect 'CREATE' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (!self::is_token_of($token, "DATABASE")) {
            throw new \ErrorException("expect 'DATABASE' after 'CREATE' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (self::is_token_of($token, "IF")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "NOT")) {
                throw new \ErrorException("expect 'NOT' after 'CREATE DATABASE IF ...' got " . self::token_to_string($token));
            }
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "EXISTS")) {
                throw new \ErrorException("expect 'EXISTS' after 'CREATE DATABASE IF NOT ...' got " . self::token_to_string($token));
            }
            $token = @$tokens[++$index];
            $obj["IFNOTEXISTS"] = 1;
        }
        list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
        if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("expect <DATABASE NAME> after 'CREATE DATABASE [IF NOT EXISTS] ...' got " . self::create($expr));
        }
        if (\count($expr["parts"]) !== 1) {
            throw new \ErrorException("<DATABASE NAME> can only have one part got " . self::create($expr));
        }
        $expr["type"] = self::T_IDENTIFIER_DATABASE;
        $obj["database"] = $expr;
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_drop($tokens, $index)
    {
        $obj = array();
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "DROP")) {
            throw new \ErrorException("expect 'DROP' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (self::is_token_of($token, "DATABASE")) {
            $obj["type"] = self::T_SQL_DROP_DATABASE;
            $token = @$tokens[++$index];
            if (self::is_token_of($token, "IF")) {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, "EXISTS")) {
                    throw new \ErrorException("expect 'EXISTS' after 'DROP DATABASE IF ...' got " . self::token_to_string($token));
                }
                $token = @$tokens[++$index];
                $obj["IFEXISTS"] = 1;
            }
            list($expr, $index) = self::get_next_expr($tokens, $index, 0);
            if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                throw new \ErrorException("expect <DATABASE NAME> after 'DROP DATABASE [IF EXISTS] ...' got " . self::create($expr));
            }
            if (\count($expr["parts"]) !== 1) {
                throw new \ErrorException("<DATABASE NAME> can only have one part got " . self::create($expr));
            }
            $expr["type"] = self::T_IDENTIFIER_DATABASE;
            $obj["database"] = $expr;
        } elseif (self::is_token_of($token, "TABLE")) {
            $obj["type"] = self::T_SQL_DROP_TABLE;
            $token = @$tokens[++$index];
            if (self::is_token_of($token, "IF")) {
                $token = @$tokens[++$index];
                if (!self::is_token_of($token, "EXISTS")) {
                    throw new \ErrorException("expect 'EXISTS' after 'DROP TABLE IF ...' got " . self::token_to_string($token));
                }
                $token = @$tokens[++$index];
                $obj["IFEXISTS"] = 1;
            }
            list($expr, $index) = self::get_next_expr($tokens, $index, 0);
            if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                throw new \ErrorException("expect <TABLE NAME> after 'DROP TABLE [IF EXISTS] ...' got " . self::create($expr));
            }
            $expr["type"] = self::T_IDENTIFIER_TABLE;
            $obj["table"] = $expr;
        } else {
            throw new \ErrorException("expect 'DATABASE' 'TABLE' after 'DROP' got " . self::token_to_string($token));
        }
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_desc($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_DESC_TABLE;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "DESC") && !self::is_token_of($token, "DESCRIBE")) {
            throw new \ErrorException("expect 'DESC' 'DESCRIBE' got " . self::token_to_string($token));
        }
        list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
        if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("expect <TABLE NAME> after 'DESC|DESCRIBE ...' got " . self::token_to_string($token));
        }
        $expr["type"] = self::T_IDENTIFIER_TABLE;
        $obj["table"] = $expr;
        list($index) = self::get_next_format($obj, $tokens, $index);
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_truncate($tokens, $index)
    {
        $obj = array();
        $obj["type"] = self::T_SQL_TRUNCATE_TABLE;
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "TRUNCATE")) {
            throw new \ErrorException("expect 'TRUNCATE' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (!self::is_token_of($token, "TABLE")) {
            throw new \ErrorException("expect 'TABLE' after 'TRUNCATE ...' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (self::is_token_of($token, "IF")) {
            $token = @$tokens[++$index];
            if (!self::is_token_of($token, "EXISTS")) {
                throw new \ErrorException("expect 'EXISTS' after 'TRUNCATE TABLE IF ...' got " . self::token_to_string($token));
            }
            $token = @$tokens[++$index];
            $obj["IFEXISTS"] = 1;
        }
        list($expr, $index) = self::get_next_expr($tokens, $index + 1, 0);
        if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
            throw new \ErrorException("expect <TABLE NAME> after 'TRUNCATE TABLE [IF EXISTS] ...' got " . self::create($expr));
        }
        $expr["type"] = self::T_IDENTIFIER_TABLE;
        $obj["table"] = $expr;
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }

    protected static function get_next_show($tokens, $index)
    {
        $obj = array();
        $token = @$tokens[$index];
        if (!self::is_token_of($token, "SHOW")) {
            throw new \ErrorException("expect 'SHOW' got " . self::token_to_string($token));
        }
        $token = @$tokens[++$index];
        if (self::is_token_of($token, "DATABASES")) {
            $obj["type"] = self::T_SQL_SHOW_DATABASES;
            $token = @$tokens[++$index];
        } elseif (self::is_token_of($token, "TABLES")) {
            $obj["type"] = self::T_SQL_SHOW_TABLES;
            $token = @$tokens[++$index];
            if (self::is_token_of($token, "FROM") || self::is_token_of($token, "IN")) {
                list($expr, $index) = self::get_next_expr($tokens, $index + 1, 100);
                if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                    throw new \ErrorException("expect <DATABASE NAME> after 'SHOW TABLES FROM ...' got " . self::create($expr));
                }
                if (\count($expr["parts"]) !== 1) {
                    throw new \ErrorException("<DATABASE NAME> can only have one part got " . self::create($expr));
                }
                $expr["type"] = self::T_IDENTIFIER_DATABASE;
                $obj["database"] = $expr;
                $token = @$tokens[$index];
            }
            if (self::is_token_of($token, "LIKE")) {
                list($expr, $index) = self::get_next_expr($tokens, $index + 1, 100);
                if (!self::is_expr_of($expr, self::T_CONSTANT_STRING)) {
                    throw new \ErrorException("expect <STRING> after 'SHOW TABLES [FROM <DATABASE NAME>] LIKE <STRING>' got " . self::token_to_string($token));
                }
                $obj["LIKE"] = $expr["expr"];
                $token = @$tokens[$index];
            }
        } elseif (self::is_token_of($token, "PROCESSLIST")) {
            $obj["type"] = self::T_SQL_SHOW_PROCESSLIST;
            $token = @$tokens[++$index];
        } elseif (self::is_token_of($token, "CREATE")) {
            $obj["type"] = self::T_SQL_SHOW_CREATE_TABLE;
            $token = @$tokens[++$index];
            if (self::is_token_of($token, "TEMPORARY")) {
                $obj["TEMPORARY"] = 1;
                $token = @$tokens[++$index];
            }
            if (!self::is_token_of($token, "TABLE")) {
                throw new \ErrorException("expect 'TABLE' after 'SHOW CREATE ...' got " . self::token_to_string($token));
            }
            list($expr, $index) = self::get_next_expr($tokens, $index + 1, 100);
            if (!self::is_expr_of($expr, self::T_IDENTIFIER_COLREF)) {
                throw new \ErrorException("expect <TABLE NAME> after 'SHOW CREATE TABLE ...' got " . self::create($expr));
            }
            $expr["type"] = self::T_IDENTIFIER_TABLE;
            $obj["table"] = $expr;
        } else {
            throw new \ErrorException("expect 'DATABASES' 'TABLES' 'CREATE' 'PROCESSLIST' after 'SHOW' got " . self::token_to_string($token));
        }
        list($index) = self::get_next_format($obj, $tokens, $index);
        list($index) = self::get_next_semicolon($obj, $tokens, $index);
        return array($obj, $index);
    }
}
