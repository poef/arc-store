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
            $this->store = \arc\store::connect('pgsql:host=localhost;dbname=arc_store_test;user=arc_store_test;password=arc_store_test');
            $this->store->initialize();
        }

        function testStoreQuery()
        {
            $qp = new \arc\store\PSQLQueryParser(array('\arc\store','tokenizer'));
            $result = $qp->parse("nodes.path='/'");
            $this->assertEquals("nodes.path='/'", $result);
            $result = $qp->parse("foo.bar='baz'");
            $this->assertEquals("nodes.data #>> '{foo,bar}'='baz'", $result);
            $result = $qp->parse("foo.bar !~ 'b%z'");
            $this->assertEquals("nodes.data #>> '{foo,bar}' not like 'b%z'", $result);
            $result = $qp->parse("foo.bar ~= 'b%z'");
            $this->assertEquals("nodes.data #>> '{foo,bar}' like 'b%z'", $result);
            $result = $qp->parse("foo ? 'bar'");
            $this->assertEquals("nodes.data #>> '{foo}'?'bar'", $result);
            $result = $qp->parse("foo.bar>3");
            $this->assertEquals("nodes.data #>> '{foo,bar}'>3",$result);
            $result = $qp->parse("foo.bar <> 'bar\\'bar'");
            $this->assertEquals("nodes.data #>> '{foo,bar}'<>'bar\\'bar'",$result);
            $result = $qp->parse("foo.bar != 'bar\\'bar'");
            $this->assertEquals("nodes.data #>> '{foo,bar}'!='bar\\'bar'",$result);
            $result = $qp->parse("foo.bar !~ 'b%z' and bar.foo = 3");
            $this->assertEquals("nodes.data #>> '{foo,bar}' not like 'b%z' and nodes.data #>> '{bar,foo}'=3", $result);
            $result = $qp->parse("(foo.bar !~ 'b%z' and bar.foo = 3)");
            $this->assertEquals("(nodes.data #>> '{foo,bar}' not like 'b%z' and nodes.data #>> '{bar,foo}'=3)", $result);
            $result = $qp->parse("(foo.bar !~ 'b%z' and bar.foo = 3) or nodes.path='/'");
            $this->assertEquals("(nodes.data #>> '{foo,bar}' not like 'b%z' and nodes.data #>> '{bar,foo}'=3) or nodes.path='/'", $result);
            $result = $qp->parse("not(foo.bar = 'bar')");
            $this->assertEquals("not(nodes.data #>> '{foo,bar}'='bar')", $result);
        }

        function testStoreParseError()
        {
            $qp = new \arc\store\PSQLQueryParser(array('\arc\store','tokenizer'));
            $this->expectException(\LogicException::class);
            $result = $qp->parse("just_a_name_with_1_number");
        }

        function testStoreParseParenthesisError()
        {
            $qp = new \arc\store\PSQLQueryParser(array('\arc\store','tokenizer'));
            $this->expectException(\LogicException::class);
            $result = $qp->parse("(parenthesis = 'unbalanced'");
        }

        function testStoreParseStringError()
        {
            $qp = new \arc\store\PSQLQueryParser(array('\arc\store','tokenizer'));
            $this->expectException(\LogicException::class);
            $result = $qp->parse("foo = 'bar");
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
            $result = $this->store->find("nodes.path ~= '/%'");
            $this->assertContainsOnly('stdClass',$result);
            $this->assertCount(2, $result);
            $result = $this->store->find("foo.bar ~= 'Ba%'");
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
            $db = new PDO('pgsql:host=localhost;dbname=arc_store_test;user=arc_store_test;password=arc_store_test');
            $db->exec('drop table objects cascade');
            $db->exec('drop table links cascade');
        }
    
    }
