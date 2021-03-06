<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OAuth2\Tests\Controller;

use OC\Authentication\Token\DefaultTokenMapper;
use OCA\OAuth2\Controller\SettingsController;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use Test\TestCase;

class SettingsControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var ClientMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $clientMapper;
	/** @var ISecureRandom|\PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;
	/** @var AccessTokenMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $accessTokenMapper;
	/** @var DefaultTokenMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $defaultTokenMapper;
	/** @var SettingsController */
	private $settingsController;

	public function setUp() {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->clientMapper = $this->createMock(ClientMapper::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->accessTokenMapper = $this->createMock(AccessTokenMapper::class);
		$this->defaultTokenMapper = $this->createMock(DefaultTokenMapper::class);

		$this->settingsController = new SettingsController(
			'oauth2',
			$this->request,
			$this->clientMapper,
			$this->secureRandom,
			$this->accessTokenMapper,
			$this->defaultTokenMapper
		);
	}

	public function testAddClient() {
		$this->secureRandom
			->expects($this->at(0))
			->method('generate')
			->with(64, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
			->willReturn('MySecret');
		$this->secureRandom
			->expects($this->at(1))
			->method('generate')
			->with(64, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
			->willReturn('MyClientIdentifier');

		$client = new Client();
		$client->setName('My Client Name');
		$client->setRedirectUri('https://example.com/');
		$client->setSecret('MySecret');
		$client->setClientIdentifier('MyClientIdentifier');

		$this->clientMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (Client $c) {
				return $c->getName() === 'My Client Name' &&
					$c->getRedirectUri() === 'https://example.com/' &&
					$c->getSecret() === 'MySecret' &&
					$c->getClientIdentifier() === 'MyClientIdentifier';
			}))->will($this->returnCallback(function (Client $c) {
				$c->setId(42);
				return $c;
			}));

		$result = $this->settingsController->addClient('My Client Name', 'https://example.com/');
		$this->assertInstanceOf(JSONResponse::class, $result);

		$data = $result->getData();

		$this->assertEquals([
			'id' => 42,
			'name' => 'My Client Name',
			'redirectUri' => 'https://example.com/',
			'clientId' => 'MyClientIdentifier',
			'clientSecret' => 'MySecret',
		], $data);
	}

	public function testDeleteClient() {
		$client = new Client();
		$client->setId(123);
		$client->setName('My Client Name');
		$client->setRedirectUri('https://example.com/');
		$client->setSecret('MySecret');
		$client->setClientIdentifier('MyClientIdentifier');

		$this->clientMapper
			->method('getByUid')
			->with(123)
			->willReturn($client);
		$this->accessTokenMapper
			->expects($this->once())
			->method('deleteByClientId')
			->with(123);
		$this->defaultTokenMapper
			->expects($this->once())
			->method('deleteByName')
			->with('My Client Name');
		$this->clientMapper
			->method('delete')
			->with($client);

		$result = $this->settingsController->deleteClient(123);
		$this->assertInstanceOf(JSONResponse::class, $result);
		$this->assertEquals([], $result->getData());
	}

	public function testGetClients() {
		$client1 = new Client();
		$client1->setId(123);
		$client1->setName('My Client Name');
		$client1->setRedirectUri('https://example.com/');
		$client1->setSecret('MySecret');
		$client1->setClientIdentifier('MyClientIdentifier');

		$client2 = new Client();
		$client2->setId(42);
		$client2->setName('My Client Name2');
		$client2->setRedirectUri('https://example.com/2');
		$client2->setSecret('MySecret2');
		$client2->setClientIdentifier('MyClientIdentifier2');

		$this->clientMapper->method('getClients')
			->willReturn([$client1, $client2]);

		$result = $this->settingsController->getClients();
		$this->assertInstanceOf(JSONResponse::class, $result);

		$data = $result->getData();

		$this->assertSame([
			[
				'id' => 123,
				'name' => 'My Client Name',
				'redirectUri' => 'https://example.com/',
				'clientId' => 'MyClientIdentifier',
				'clientSecret' => 'MySecret',
			],
			[
				'id' => 42,
				'name' => 'My Client Name2',
				'redirectUri' => 'https://example.com/2',
				'clientId' => 'MyClientIdentifier2',
				'clientSecret' => 'MySecret2',
			],
		], $data);
	}
}
