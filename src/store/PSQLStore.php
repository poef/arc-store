<?php
/*
    FIXME/TODO
        - db execute in resultbuilder doen
        - hier alleen de where clause opbouwen
*/
namespace arc\store;

final class PSQLStore {
    
    public function __construct($path, $db, $queryParser, $resultBuilder)
    {
        $this->path          = \arc\path::collapse($path);
        $this->db            = $db;
        $this->queryParser   = $queryParser;
        $this->resultBuilder = $resultBuilder;
    }

    public function cd($path)
    {
        return new self( \arc\path::collapse($path, $this->path), $this->db, $this->queryParser );
    }

    public function find($query)
    {
        list($query, $params) 
                = $this->queryParser->parse($query, $this->path);
        $query  = $this->db->prepare($query);
        $result = $this->db->execute($query, $params);
        return $this->resultBuilder($result, $this);
    }

    public function get($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        if ( $this->exists($path) ) {
            $query  = $this->db->prepare('select nodes.path, nodes.parent, object.data, object.ctime, object.mtime '
            .'from nodes, object where nodes.object-id=object.object-id and nodes.path=:path');
            $result = $this->db->execute($query, [':path' => $path ]);
        }
        return $this->resultBuilder($result, $this);
    }

    public function parents($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        if ( $this->exists($path) ) {
            $query  = '';
            $result = $this->db->execute($query, [':path' => $path]);
        }
        return $this->resultBuilder($result, $this);
    }

    public function ls($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        if ( $this->exists($path) ) {
            $query  = '';
            $result = $this->db->execute($query, [':path' => $path]);
        }
        return $this->resultBuilder($result, $this);
    }

    public function exists($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = false;
        if ( $this->exists($path) ) {
            $query  = 'select nodes.id from nodes where path=":path"';
            $result = $this->db->execute($query, [':path' => $path]);
        }
        return (bool)$result;
    }
}