<?php
/*
    FIXME/TODO
        - db execute in resultbuilder doen
        - hier alleen de where clause opbouwen
*/
namespace arc\store;

final class PSQLStore {

    private $db;
    private $queryParser;
    private $resultBuilder;
    private $path;

    public function __construct($db = null, $queryParser = null, $resultBuilder = null,$path = '/')
    {
        $this->db            = $db;
        $this->queryParser   = $queryParser;
        $this->resultBuilder = $resultBuilder;
        $this->path          = \arc\path::collapse($path);
    }

    public function cd($path)
    {
        return new self( \arc\path::collapse($path, $this->path), $this->db, $this->queryParser, $this->resultBuilder );
    }

    public function find($query)
    {
        $query = $this->queryParser->parse($query, $this->path);
        $query  = $this->db->prepare($query);
        $result = $query->execute();
        return call_user_func($this->resultBuilder, $query);
    }

    public function get($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where path=:path');
        $result = $query->execute($query, [':path' => $path ]);
        return call_user_func($this->resultBuilder, $query);
    }

    public function parents($path='', $top='/')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where lower(path)=lower(substring(:path,1,length(path))) and lower(path) LIKE lower(:top) order by path');
        $result = $query->execute([':path' => $path, ':top' => $top.'%']);
        return call_user_func($this->resultBuilder, $query);
    }

    public function ls($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where parent=:path');
        $result = $query->execute([':path' => $path]);
        return call_user_func($this->resultBuilder, $query);
    }

    public function exists($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $query  = $this->db->prepare('select count(*) from nodes where path=:path');
        $result = $query->execute([':path' => $path]);
        return ($query->fetchColumn(0)>0);
    }

    public function initialize() {
        if ($this->exists('/')) {
            return false;
        }
        $queries = [];
        $queries[0] = <<<EOF
create table objects (
    parent text not null ,
    name   text not null,
    data   jsonb not null,
    ctime  timestamp default current_timestamp,
    mtime  timestamp default current_timestamp
);
EOF;
        $queries[1] = "create unique index path on objects ((parent || name || '/'));";
        $queries[2] = "create unique index lower_path on objects ((lower(parent) || lower(name) || '/' ));";
        $queries[3] = "create index datagin on objects using gin (data);";
        $queries[4] = "create view nodes as select (parent || name || '/') as path, * from objects;";
        foreach ( $queries as $query ) {
            $result = $this->db->exec($query);
            if ($result===false) {
                return false;
            }
        }
        return $this->save(\arc\lambda::prototype([
            'name' => 'Root'
        ]),'/');
    }

    public function save($data, $path='') {
        $path   = \arc\path::collapse($path, $this->path);
        $parent = ($path=='/' ? '' : \arc\path::parent($path));
        $name = ($path=='/' ? '' : basename($path));
        $query = $this->db->prepare("insert into objects (parent, name, data) values(:parent, :name, :data);");
        return $query->execute([
            ':parent' => $parent,
            ':name'   => $name,
            ':data'   => json_encode($data)
        ]);
    }

    public function delete($path = '') {
        $path   = \arc\path::collapse($path, $this->path);
        $query = $this->db->prepare("delete from nodes where path like :path");
        return $query->execute([':path' => $path.'%']);
    }
}