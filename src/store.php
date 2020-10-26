<?php

namespace arc;

final class store {

    /**
     * @return mixed current store
     */
    public static function getStore()
    {
        $context = \arc\context::$context;
        return $context->arcStore;
    }

    /**
     * Connects to an ARC object store
     * @param $dsn postgresql connection string
     * @param null $resultHandler handler that executes the sql query
     * @return store\PSQLStore the store
     */
    public static function connect($dsn, $resultHandler=null)
    {
        $db = new \PDO($dsn);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if (strpos($dsn, 'mysql:')===0) {
            $storeType = '\arc\store\MySQL';
        }
        if (strpos($dsn, 'pgsql:')===0) {
            $storeType = '\arc\store\PSQL';
        }
        if (!$storeType) {
            throw new \arc\ConfigError('Unknown database type');
        }
        $className = $storeType.'Store';
        if (!$resultHandler) {
            $resultHandler = array($className, 'defaultResultHandler');
        }
        $queryParserClassName = $storeType.'QueryParser';
        $store = new $className(
            $db, 
            new $queryParserClassName(array('\arc\store','tokenizer')), 
            $resultHandler($db)
        );
        \arc\context::push([
            'arcStore' => $store
        ]);
        return $store;
    }

    /**
     * disconnects the store
     */
    public static function disconnect()
    {
        \arc\context::pop();
    }

    /**
     *
     * @param $path
     * @return mixed
     */
    public static function cd($path)
    {
        return self::getStore()->cd($path);
    }

    /**
     * @param $query
     * @return mixed
     */
    public static function find($query)
    {
        return self::getStore()->find($query);
    }

    /**
     * @param $path
     * @return mixed
     */
    public static function parents($path)
    {
        return self::getStore()->parents($path);
    }

    /**
     * @param $path
     * @return mixed
     */
    public static function ls($path)
    {
        return self::getStore()->ls($path);
    }

    /**
     * @param $path
     * @return mixed
     */
    public static function get($path)
    {
        return self::getStore()->get($path);
    }

    /**
     * @param $path
     * @return mixed
     */
    public static function exists($path)
    {
        return self::getStore()->exists($path);
    }

    /**
     * yields the tokens in the search query expression
     * @param string $query
     * @return \Generator
     * @throws \LogicException
     */
        
    public static function tokenizer($query) {
    /*
        query syntax:
        part     = ( name '.' )* name compare value
        query    = part | part operator (not) part | (not) '(' query ')'  
        operator = 'and' | 'or'
        not      = 'not'
        compare  = '<' | '>' | '=' | '>=' | '<=' | 'like' | 'not like' | '?'
        value    = number | string
        number   = [0-9]* ('.' [0-9]+)?
        string   = \' [^\\1]* \'

        e.g: "contact.address.street like '%Crescent%' and ( name.firstname = 'Foo' or name.lastname = 'Bar')"
    */
        $token = <<<'REGEX'
/^\s*
(
            (?<compare>
                <= | >= | <> | < | > | = | != | ~= | !~ | \?
            )
            |
            (?<operator>
                and | or
            )
            |
            (?<not>
                not
            )
            |
            (?<name>
                [a-z]+[a-z0-9_-]*
                (?: \. [a-z]+[a-z0-9_-]* )*
            )
            |
            (?<number>
                [+-]?[0-9](\.[0-9]+)?
            )
            |
            (?<string>
                (?<quote>(?<![\\\\])[\'])
                (?<content>(?:.(?!(?<![\\\\])(?P=quote)))*.?)
                (?P=quote) 
            )
            |
            (?<parenthesis_open>
                \(
            )
            |
            (?<parenthesis_close>
                \)
            )
)/x
REGEX;
        do {
            $result = preg_match($token, $query, $matches, PREG_OFFSET_CAPTURE);
            if ($result) {
                $query = substr($query, strlen($matches[0][0]));
                // todo: swap filters, first remove numeric keys
                yield array_filter(
                    array_filter($matches, function($match) {
                        return $match[0];
                    }),
                    function($key) {
                        return !is_int($key);
                    }, ARRAY_FILTER_USE_KEY
                );
            }
        } while($result);
        if ( trim($query) ) {
            throw new \LogicException('Could not parse '.$query);
        }
    }

}
