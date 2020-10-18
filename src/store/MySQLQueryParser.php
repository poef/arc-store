<?php

namespace arc\store;

final class MySQLQueryParser {

    private $tokenizer;

	public function __construct($tokenizer) {
		$this->tokenizer = $tokenizer;
	}

    /**
     * @param string $query
     * @return string postgresql 'where' part of the sql query
     * @throws \LogicException when a parse error occurs
     */
    public function parse($query)
    {
        $indent   = 0;
        $part     = '';
        $sql      = '';
        $position = 0;
        $expect   = 'name|parenthesis_open|not';
        
        foreach( call_user_func($this->tokenizer, $query) as $token ) {
            $type = key($token);
            list($token, $offset)=$token[$type];
            if ( !preg_match("/^$expect$/",$type) ) {
                throw new \LogicException('Parse error at '.$position.': expected '.$expect.', got '.$type.': '
                    .(substr($query,0, $position)." --> ".substr($query,$position)) );
            }
            switch($type) {
                case 'number':
                case 'string':
                    $sql   .= $part.$token;
                    $part   = '';
                    $expect = 'operator|parenthesis_close';
                break;
                case 'name':
                    switch ($token) {
                        case 'nodes.path':
                        case 'nodes.parent':
                        case 'nodes.name':
                        case 'nodes.mtime':
                        case 'nodes.ctime':
                            $part = $token;
                        break;
                        default:
                            $part = "JSON_UNQUOTE(JSON_EXTRACT( nodes.data, '$.".$token."'))";
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
                            $part.= ' IS NOT NULL';
                            str_replace($part, '->>', '->');
                        break;
                        case '~=':
                            $part.=' like ';
                        break;
                        case '!~':
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
            throw new \LogicException('unbalanced parenthesis');
        } else if ( trim($part) ) {
            $position -= strlen($token);
            throw new \LogicException('parse error at '.$position.': '.(substr($query,0, $position)." --> ".substr($query,$position)));
        } else {
            return $sql;
        }
    }

}
