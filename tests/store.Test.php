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

    class TestStore extends PHPUnit_Framework_TestCase
    {
    	var $db = null;
    	var $store = null;

		function __construct()
		{
			parent::__construct();
			$this->db = new PDO('pgsql:host=localhost;dbname=arc_store_test;user=auke;password=fropfrop');
			$this->store = \arc\store::connect($this->db);
			$this->store->initialize();
		}

		function testStoreQuery()
		{
			$qp = new \arc\store\PSQLQueryParser();
			$result = $qp->parse("nodes.path='/'");
			$this->assertEquals("select * from nodes where nodes.path='/'", $result);
			$result = $qp->parse("foo.bar='baz'");
			$this->assertEquals("select * from nodes where nodes.data #>> '{foo,bar}'='baz'", $result);
		}


		function testStoreSave()
		{
			$result = $this->store->save(\arc\lambda::prototype([
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
		}

		function testStoreLs()
		{
			$result = $this->store->ls('/');
			$this->assertContainsOnly('stdClass',$result);
			$this->assertCount(1, $result);
		}

		function testStoreFind()
		{
			$result = $this->store->find("nodes.path~='/%'");
			$this->assertContainsOnly('stdClass',$result);
			$this->assertCount(2, $result);
			$result = $this->store->find("foo.bar~='Ba%'");
			$this->assertCount(1, $result);
		}

		function testEnd()
		{
			$this->db->exec('drop table objects cascade');
		}
	
    }
