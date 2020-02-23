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
        if (!$resultHandler) {
            $resultHandler = store\PSQLStore::defaultResultHandler($db);
        }
        $store = new store\PSQLStore(
            $db, 
            new store\PSQLQueryParser(), 
            $resultHandler
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


}
