<?php

namespace arc\store;

final class PSQLQueryParser {

    /*
        query syntax:
        part     = ( name '.' )* name compare value
        query    = part | part operator part | (' query ')' 
        operator = 'and' | 'or'
        compare  = '<' | '>' | '=' | '~=' | '!='
        value    = number | string
        number   = [0-9]* ('.' [0-9]+)?
        string   = (['"]) [^\\1]* \\1
    */

    private function tokens($query)
    {
        $token = <<<'EOF'
/^\s*
(
            (?<operator>
                and | or
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
                (?<quote>(?<![\\\\])[\'"])
                (?<content>(?:.(?!(?<![\\\\])(?P=quote)))*.?)
                (?P=quote) 
            )
            |
            (?<compare>
                < | > | = | ~= | \!= | !~ | ~! | <>
            )
            |
            (?<parenthesis>
                \( | \)
            )
)/x
EOF;
        do {
            $result = preg_match($token, $query, $matches, PREG_OFFSET_CAPTURE);
            if ($result) {
                $query = substr($query, strlen($matches[0][0]));
                yield array_filter(
                    array_filter($matches, function($match) {
                        return $match[0];
                    }),
                    function($key) {
                        return !is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY
                );
            }
        } while($result);
        if ( trim($query) ) {
            throw new \Exception('Could not parse '.$query);
        }
    }

    public function parse($query, $path='')
    {
        $indent = 0;
        $part   = '';
        $sql    = '';
        $position = 0;
        foreach( $this->tokens($query) as $token ) {
            $type = key($token);
            list($token, $offset)=$token[$type];
            switch($type) {
                case 'number':
                case 'string':
                    $sql .= $part.$token;
                    $part = '';
                break;
                case 'name':
                    switch ($token) {
                        case 'node.path':
                        case 'node.parent':
                        case 'object.mtime':
                        case 'object.ctime':
                            $part = $token;
                        break;
                        default:
                            $part = "objects.data::json#>'{".str_replace('.',',',$token)."}'";
                        break;
                    }
                break;
                case 'compare':
                    switch( $token ) {
                        case '>':
                        case '<':
                        case '=':
                            $part.=$token;
                        break;
                        case '~=':
                            $part.=' like ';
                        break;
                        case '<>':
                        case '!=':
                            $part.='<>';
                        break;
                        case '!~':
                        case '~!':
                            $part.=' not like ';
                        break;
                    }
                break;
                case 'operator':
                    $sql .= ' '.$token.' ';
                break;
                case 'parenthesis':
                    $sql .= $token;
                    if ( $token == '(' ) {
                        $indent++;
                    } else {
                        $indent--;
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