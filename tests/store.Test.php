<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the
     * LICENSE
     * file that was distributed with this source code.
     */

    class TestStore extends PHPUnit\Framework\TestCase
    {
        var $store = null;

        function __construct()
        {
            parent::__construct();
            $this->store = \arc\store::connect('pgsql:host=localhost;dbname=arc_store_test;user=arc_store_test;password=test');
            $this->store->initialize();
        }

        function testStoreQuery()
        {
            $qp = new \arc\store\PSQLQueryParser();
            $result = $qp->parse("nodes.path='/'");
            $this->assertEquals("nodes.path='/'", $result);
            $result = $qp->parse("foo.bar='baz'");
            $this->assertEquals("nodes.data #>> '{foo,bar}'='baz'", $result);
        }


        function testStoreSave()
        {
            $result = $this->store->save(\arc\prototype::create([
                'name' => 'Foo',
                'foo' => [
                    'bar' => 'Baz',
                    'int' => 10
                ]
            ]), '/foo/');
            $this->assertTrue($result);
        }

        function testStoreExists()
        {
            $result = $this->store->exists('/foo/');
            $this->assertTrue($result);
            $result = $this->store->exists('/no-i-dont/');
            $this->assertFalse($result);
        }

        function testStoreLs()
        {
            $result = $this->store->ls('/');
            $this->assertContainsOnly('stdClass',$result);
            $this->assertCount(1, $result);
            $result = $this->store->cd('/foo/')->ls();
            $this->assertCount(0, $result);
        }

        function testStoreFind()
        {
            $result = $this->store->find("nodes.path like '/%'");
            $this->assertContainsOnly('stdClass',$result);
            $this->assertCount(2, $result);
            $result = $this->store->find("foo.bar like 'Ba%'");
            $this->assertCount(1, $result);
        }

        function testStoreDelete()
        {
            $result = $this->store->delete('/foo/');
            $this->assertTrue($result);
            $result = $this->store->exists('/foo/');
            $this->assertFalse($result);
        }

        public static function tearDownAfterClass() :void
        {
            $db = new PDO('pgsql:host=localhost;dbname=arc_store_test;user=arc_store_test;password=test');
            $db->exec('drop table objects cascade');
            $db->exec('drop table links cascade');
        }
    
    }
