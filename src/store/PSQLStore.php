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

	public function initialize() {
		try {
			if ($this->exists('/')) {
				return false;
			}
		} catch(\Exception $e) {
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
			$result = $this->db->execute($query, []);
			if (!$result) {
				return false;
			}
		}
		return $this->save([],'/');
	}

	public function save($data, $path='') {
        $path   = \arc\path::collapse($path, $this->path);
		$parent = ($path=='/' ? '' : \arc\path::parent($path));
		$name = ($path=='/' ? '' : dirname($path));
		$query = "insert into objects (parent, name, data) values(:parent, :name, :data);";
		return $this->db->execute($query, ['parent' => $parent, 'name' => $name, 'data' => json_serialize($data)]);
	}

	public function delete($path = '') {
        $path   = \arc\path::collapse($path, $this->path);
		$parent = ($path=='/' ? '' : \arc\path::parent($path));
		$name = ($path=='/' ? '' : dirname($path));
		$query = "delete from objects where parent like ':path%'";
		if ( $this->db->execute($query, ['path' => $path])) {
			$query = "delete from objects where parent=:parent and name=:name";
			return $this->db->execute($query, ['parent' => $parent, 'name' => $name]);
		}
		return false;	
	}
}