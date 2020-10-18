
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
     * @throws \LogicException
     */
        
    private function tokens($query)
    {
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
