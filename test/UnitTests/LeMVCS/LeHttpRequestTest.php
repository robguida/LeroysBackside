<?php
/**
 * Created by PhpStorm.
 * User: rob
 * Date: 11/10/2018
 * Time: 11:04 AM
 */

namespace LeroyTest\LeMVCS;

use Leroy\LeMVCS\LeHttpRequest;
use LeroyTestLib\LeroyUnitTestAbstract;

class LeHttpRequestTest extends LeroyUnitTestAbstract
{
    private $_POST = ['action' => 'create', 'first_name' => 'Leroy', 'last_name' => 'Brown'];
    private $_GET = ['action' => 'read', 'id' => 22];
    private $argv = ['action' => 'update', 'id' => 44, 'first_name' => 'LeRoy'];

    public function testLoadPost()
    {
        $request = LeHttpRequest::loadPost($this->_POST);
        $this->assertInstanceOf('Leroy\LeMVCS\LeHttpRequest', $request);
        $this->assertEquals(LeHttpRequest::METHOD_POST, $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_GET == $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_CLI == $request->getMethod());
        $this->assertEquals($this->_POST, $request->get());
        $this->assertEquals('create', $request->get('action'));
        $this->assertEquals('Leroy', $request->get('first_name'));
        $this->assertEquals('Brown', $request->get('last_name'));
        $this->assertFalse($request->get('key_does_not_exist'));
    }

    public function testLoadGet()
    {
        $request = LeHttpRequest::loadGet($this->_GET);
        $this->assertInstanceOf('Leroy\LeMVCS\LeHttpRequest', $request);
        $this->assertEquals(LeHttpRequest::METHOD_GET, $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_POST == $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_CLI == $request->getMethod());
        $this->assertEquals($this->_GET, $request->get());
        $this->assertEquals('read', $request->get('action'));
        $this->assertEquals('22', $request->get('id'));
        $this->assertFalse($request->get('key_does_not_exist'));
    }

    public function testLoadCli()
    {
        $request = LeHttpRequest::loadArgv($this->argv);
        $this->assertInstanceOf('Leroy\LeMVCS\LeHttpRequest', $request);
        $this->assertEquals(LeHttpRequest::METHOD_CLI, $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_GET == $request->getMethod());
        $this->assertFalse(LeHttpRequest::METHOD_POST == $request->getMethod());
        $this->assertEquals($this->argv, $request->get());
        $this->assertEquals('update', $request->get('action'));
        $this->assertEquals('44', $request->get('id'));
        $this->assertEquals('LeRoy', $request->get('first_name'));
        $this->assertFalse($request->get('key_does_not_exist'));
    }

    public function testNoneOfTheseIsQuiteLikeTheOther()
    {
        $array = ['just' => 'a', 'typical' => 'array'];
        $requestPost = LeHttpRequest::loadPost($array);
        $requestGet = LeHttpRequest::loadGet($array);
        $requestCli = LeHttpRequest::loadArgv($array);
        $this->assertNotEquals($requestPost, $requestGet);
        $this->assertNotEquals($requestPost, $requestCli);
        $this->assertNotEquals($requestGet, $requestCli);
        $post_resource_num = spl_object_hash($requestPost);
        $get_resource_num = spl_object_hash($requestGet);
        $cli_resource_num = spl_object_hash($requestCli);
        $this->assertNotEquals($post_resource_num, $get_resource_num);
        $this->assertNotEquals($post_resource_num, $cli_resource_num);
        $this->assertNotEquals($get_resource_num, $cli_resource_num);
    }

    public function testSingletonObjectCreation()
    {
        $array1 = ['just' => 'a', 'typical' => 'array'];
        $array2 = ['just' => 'another', 'night' => 'in', 'the' => 'city'];
        $requestPost1 = LeHttpRequest::loadPost($array1);
        $requestPost2 = LeHttpRequest::loadPost($array2);
        $requestGet1 = LeHttpRequest::loadGet($array1);
        $requestGet2 = LeHttpRequest::loadGet($array2);
        $requestCli1 = LeHttpRequest::loadArgv($array1);
        $requestCli2 = LeHttpRequest::loadArgv($array2);
        $this->assertEquals($requestPost1, $requestPost2);
        $this->assertFalse($array2 == $requestPost2->get());
        $this->assertTrue($array1 == $requestPost2->get());
        $this->assertEquals($requestGet1, $requestGet2);
        $this->assertFalse($array2 == $requestGet2->get());
        $this->assertTrue($array1 == $requestGet2->get());
        $this->assertEquals($requestCli1, $requestCli2);
        $this->assertFalse($array2 == $requestCli2->get());
        $this->assertTrue($array1 == $requestCli2->get());
        $post1_resource_num = spl_object_hash($requestPost1);
        $post2_resource_num = spl_object_hash($requestPost2);
        $get1_resource_num = spl_object_hash($requestGet1);
        $get2_resource_num = spl_object_hash($requestGet2);
        $cli1_resource_num = spl_object_hash($requestCli1);
        $cli2_resource_num = spl_object_hash($requestCli2);
        $this->assertEquals($post1_resource_num, $post2_resource_num);
        $this->assertEquals($get1_resource_num, $get2_resource_num);
        $this->assertEquals($cli1_resource_num, $cli2_resource_num);
    }

    public function testAdd()
    {
        $request = LeHttpRequest::loadPost($this->_POST);
        $this->assertTrue($request->add('middle_name', 'Francis'));
        $this->assertEquals('Francis', $request->get('middle_name'));
    }

    public function testRemove()
    {
        $request = LeHttpRequest::loadPost($this->_POST);
        $this->assertTrue($request->remove('first_name'));
        $this->assertFalse($request->remove('key_does_not_exist'));
    }

    public function testLoadArray()
    {
        $request = LeHttpRequest::loadPost($this->_POST);
        $this->assertEquals('create', $request->get('action'));
        $this->assertFalse($request->get('id'));
        $request->loadArray($this->_GET);
        $this->assertEquals('read', $request->get('action'));
        $this->assertEquals('22', $request->get('id'));
    }

    public function testLoadArrayWillNotChangeMethod()
    {
        $request = LeHttpRequest::loadPost($this->_POST);
        $this->assertEquals(LeHttpRequest::METHOD_POST, $request->getMethod());
        $request->loadArray($this->argv);
        $this->assertEquals(LeHttpRequest::METHOD_POST, $request->getMethod());
        $request->loadArray($this->_GET);
        $this->assertEquals(LeHttpRequest::METHOD_POST, $request->getMethod());
    }
}
