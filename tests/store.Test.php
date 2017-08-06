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

    class StoreHash extends PHPUnit_Framework_TestCase
    {
		function testStoreQuery()
		{
			$qp = new \arc\store\PSQLQueryParser();
			//$result = $qp->parse('nodes.path="/"');
			//$this->assertEquals('nodes.path="/"', $result);
			$result = $qp->parse('foo.bar="baz"');
			$this->assertEquals("objects.data #>> '{foo,bar}'=\"baz\"", $result);
		}

		function testStoreInit()
		{
			$db = new PDO('psql:host=localhost;dbname=auke_store;user=auke;password=fropfrop');
			$store = \arc\store::connect($db);
			$store->initialize();
		}
	
    }
