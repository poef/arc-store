<?php
namespace arc\store;

final class PSQLStore {

    private $db;
    private $queryParser;
    private $resultHandler;
    private $path;

    public function __construct($db = null, $queryParser = null, $resultHandler = null,$path = '/')
    {
        $this->db            = $db;
        $this->queryParser   = $queryParser;
        $this->resultHandler = $resultHandler;
        $this->path          = \arc\path::collapse($path);
    }

    public function cd($path)
    {
        return new self( \arc\path::collapse($path, $this->path), $this->db, $this->queryParser, $this->resultHandler );
    }

    public function find($query)
    {
        $query = $this->queryParser->parse($query, $this->path);
        $query  = $this->db->prepare($query);
        $result = $query->execute();
        return call_user_func($this->resultHandler, $query);
    }

    public function get($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where path=:path');
        $result = $query->execute($query, [':path' => $path ]);
        return call_user_func($this->resultHandler, $query);
    }

    public function parents($path='', $top='/')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where lower(path)=lower(substring(:path,1,length(path))) and lower(path) LIKE lower(:top) order by path');
        $result = $query->execute([':path' => $path, ':top' => $top.'%']);
        return call_user_func($this->resultHandler, $query);
    }

    public function ls($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $result = null;
        $query  = $this->db->prepare('select * from nodes where parent=:path');
        $result = $query->execute([':path' => $path]);
        return call_user_func($this->resultHandler, $query);
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
//        $queries[] = "create extension pgcrypto;";
        $queries[] = <<<EOF
create table objects (
    id     uuid primary key default gen_random_uuid(),
    parent text not null ,
    name   text not null,
    data   jsonb not null,
    ctime  timestamp default current_timestamp,
    mtime  timestamp default current_timestamp,
    UNIQUE(parent,name)
);
EOF;
        $queries[] = "create unique index path on objects ((parent || name || '/'));";
        $queries[] = "create unique index lower_path on objects ((lower(parent) || lower(name) || '/' ));";
        $queries[] = "create index datagin on objects using gin (data);";
        $queries[] = "create view nodes as select (parent || name || '/') as path, * from objects;";
        $queries[] = <<<EOF
create table links (
    from_id  uuid references objects(id),
    to_id    uuid references objects(id),
    relation text not null,
    UNIQUE(from_id,to_id)
);
EOF;
        $queries[] = "create index link_from on links(from_id);";
        $queries[] = "create index link_to on links(to_id);";
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
        $queryStr = <<<EOF
insert into objects (parent, name, data) 
values (:parent, :name, :data) 
on conflict(parent, name) do update 
  set data = :data;
EOF;
        $query = $this->db->prepare($queryStr);
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
