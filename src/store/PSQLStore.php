<?php
namespace arc\store;

final class PSQLStore {

    private $db;
    private $queryParser;
    private $resultHandler;
    private $path;

    /**
     * PSQLStore constructor.
     * @param null $db
     * @param null $queryParser
     * @param null $resultHandler
     * @param string $path
     */
    public function __construct($db = null, $queryParser = null, $resultHandler = null, $path = '/')
    {
        $this->db            = $db;
        $this->queryParser   = $queryParser;
        $this->resultHandler = $resultHandler;
        $this->path          = \arc\path::collapse($path);
    }

    /**
     * change the current path, returns a new store instance for that path
     * @param string $path
     * @return PSQLStore
     */
    public function cd($path)
    {
        return new self( $this->db, $this->queryParser, $this->resultHandler, \arc\path::collapse($path, $this->path) );
    }

    /**
     * creates sql query for the search query and returns the resulthandler
     * @param string $query
     * @param string $path
     * @return mixed
     */
    public function find($query, $path='')
    {
		$path = \arc\path::collapse($path, $this->path);
		$sql  = $this->queryParser->parse($query, $path);
        return call_user_func( $this->resultHandler, $sql, [] );
    }

    /**
     * get a single object from the store by path
     * @param string $path
     * @return mixed
     */
    public function get($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        return call_user_func($this->resultHandler, 'path=:path', [':path' => $path]);
    }

    /**
     * list all parents, including self, by path, starting from the root
     * @param string $path
     * @param string $top
     * @return mixed
     */
    public function parents($path='', $top='/')
    {
        $path   = \arc\path::collapse($path, $this->path);
        return call_user_func(
            $this->resultHandler,
            /** @lang sql */
            'lower(path)=lower(substring(:path,1,length(path))) '
            . ' and lower(path) LIKE lower(:top) order by path',
            [':path' => $path, ':top' => $top.'%']
        );
    }

    /**
     * list all child objects by path
     * @param string $path
     * @return mixed
     */
    public function ls($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        return call_user_func(
			$this->resultHandler,
            'parent=:path',
            [':path' => $path]
        );
    }

    /**
     * returns true if an object with the given path exists
     * @param string $path
     * @return bool
     */
    public function exists($path='')
    {
        $path   = \arc\path::collapse($path, $this->path);
        $query  = $this->db->prepare('select count(*) from nodes where path=:path');
        $result = $query->execute([':path' => $path]);
        return ($query->fetchColumn(0)>0);
    }

    /**
     * initialize the postgresql database, if it wasn't before
     * @return bool|mixed
     */
    public function initialize() {
        if ($this->exists('/')) {
            return false;
        }
        $queries = [];
//        $queries[] = "create extension pgcrypto;";
        $queries[0] = <<<SQL
create table objects (
    id     uuid primary key default gen_random_uuid(),
    parent text not null ,
    name   text not null,
    data   jsonb not null,
    ctime  timestamp default current_timestamp,
    mtime  timestamp default current_timestamp,
    UNIQUE(parent,name)
);
SQL;
        $queries[] = "create unique index path on objects ((parent || name || '/'));";
        $queries[] = "create unique index lower_path on objects ((lower(parent) || lower(name) || '/' ));";
        $queries[] = "create index datagin on objects using gin (data);";
        $queries[] = "create view nodes as select (parent || name || '/') as path, * from objects;";
        $queries[] = <<<SQL
create table links (
    from_id  uuid references objects(id),
    to_id    uuid references objects(id),
    relation text not null,
    UNIQUE(from_id,to_id)
);
SQL;
        $queries[] = "create index link_from on links(from_id);";
        $queries[] = "create index link_to on links(to_id);";
        foreach ( $queries as $query ) {
            $result = $this->db->exec($query);
            if ($result===false) {
                return false;
            }
        }
        return $this->save(\arc\object::prototype([
            'name' => 'Root'
        ]),'/');
    }

    /**
     * save (insert or update) a single object on the given path
     * @param $data
     * @param string $path
     * @return mixed
     */
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

    /**
     * remove the object with the given path and all its children
     * won't remove the root object ever
     * @param string $path
     * @return mixed
     */
    public function delete($path = '') {
        $path   = \arc\path::collapse($path, $this->path);
        $query = $this->db->prepare("delete from nodes where path like :path and path!='/'");
        return $query->execute([':path' => $path.'%']);
    }
}
