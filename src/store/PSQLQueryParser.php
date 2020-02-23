<?php

namespace arc\store;

final class PSQLQueryParser {

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

    /**
     * yields the tokens in the search query expression
     * @param string $query
     * @return \Generator
     * @throws \Exception
     */
        
    private function tokens($query)
    {
        $token = <<<'REGEX'
/^\s*
(
            (?<operator>
                and | or
            )
            |
            (?<not>
                not
            )
            |
            (?<compare>
                < | > | = | <= | >= | <> | != | like | not like | \?
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
            throw new \Exception('Could not parse '.$query);
        }
    }

    /**
     * @param string $query
     * @return string postgresql 'where' part of the sql query
     * @throws \Exception when a parse error occurs
     */
    public function parse($query)
    {
        $indent   = 0;
        $part     = '';
        $sql      = '';
        $position = 0;
        $expect   = 'name|parenthesis';
        
        foreach( $this->tokens($query) as $token ) {
            $type = key($token);
            list($token, $offset)=$token[$type];
            if ( !preg_match("/^$expect$/",$type) ) {
                throw new \Exception('Parse error at '.$position.': expected '.$expect.', got '.$type.': '
                    .(substr($query,0, $position)." --> ".substr($query,$position)) );
            }
            switch($type) {
                case 'number':
                case 'string':
                    $sql   .= $part.$token;
                    $part   = '';
                    $expect = ['operator','parenthesis_close'];
                break;
                case 'name':
                    switch ($token) {
                        case 'nodes.path':
                        case 'nodes.parent':
                        case 'nodes.mtime':
                        case 'nodes.ctime':
                            $part = $token;
                        break;
                        default:
                            $part = "nodes.data #>> '{".str_replace('.',',',$token)."}'";
                        break;
                    }
                    $expect = 'compare';
                break;
                case 'compare':
                    switch( $token ) {
                        case '>':
                        case '>=':
                        case '<':
                        case '<=':
                        case '=':
                        case '<>':
                        case '!=':
                            $part.=$token;
                        break;
                        case '?':
                            $part.=$token;
                            str_replace($part, '#>>', '#>');
                        break;
                        case 'like':
                            $part.=' like ';
                        break;
                        case 'not like':
                            $part.=' not like ';
                        break;
                    }
                    $expect = 'number|string';
                break;
                case 'not':
                    $sql .= $token;
                    $expect = 'name|parenthesis_open';
                break;
                case 'operator':
                    $sql .= ' '.$token.' ';
                    $expect = 'name|parenthesis_open|not';
                break;
                case 'parenthesis_open':
                    $sql .= $token;
                    $indent++;
                    $expect = 'name|parenthesis_open|not';
                break;
                case 'parenthesis_close':
                    $sql .= $token;
                    $indent--;
                    if ( $indent>0 ) {
                        $expect = 'operator|parenthesis_close';
                    } else {
                        $expect = 'operator';
                    }
                break;
            }
            $position += $offset + strlen($token);
        }
        if ( $indent!=0 ) {
            throw new \Exception('unbalanced parenthesis');
        } else if ( trim($part) ) {
            $position -= strlen($token);
            throw new \Exception('parse error at '.$position.': '.(substr($query,0, $position)." --> ".substr($query,$position)));
        } else {
            return $sql;
        }
    }

}
