<?php
    namespace arc\store;

    abstract class DBStore {
        public static function defaultResultHandler($db = null)
        {
            if (!$db) {
                $db = self::getDb();
            }
            return function($query, $args) use ($db) {
                $q = $db->prepare('select * from nodes where '.$query);
                $result = $q->execute($args);
                $dataset = [];
                while ( $data = $q->fetch(\PDO::FETCH_ASSOC) ) {
                    $value = (object) $data;
                    $value->data = json_decode($value->data);
                    $value->ctime = strtotime($value->ctime);
                    $value->mtime = strtotime($value->mtime);
                    $path = $value->parent.$value->name.'/';
                    $dataset[$path] = $value;
                }
                return $dataset;
            };
        }

        public static function generatorResultHandler($db = null)
        {
            if (!$db) {
                $db = self::getDb();
            }
            return function($query, $args) use ($db) {
                $q = $db->prepare('select * from nodes where '.$query);
                $result = $q->execute($args);
                $data = $q->fetch(\PDO::FETCH_ASSOC);
                if (!$data) {
                    yield $data;
                }
                while ($data) {
                    $value = (object) $data;
                    $value->data = json_decode($value->data);
                    $value->ctime = strtotime($value->ctime);
                    $value->mtime = strtotime($value->mtime);
                    $path = $value->path;
                    yield $path => $value;
                    $data = $q->fetch(\PDO::FETCH_ASSOC);
                }
            };
        }
    }