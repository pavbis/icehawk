<?php
/**
 * @author h.woltersdorf
 */

namespace Fortuneglobe\IceHawk\Test\Unit\Commands;

use Fortuneglobe\IceHawk\Requests\PostRequest;
use Fortuneglobe\IceHawk\Requests\UploadedFileInfo;
use Fortuneglobe\IceHawk\Test\Unit\Fixtures\TestCommand;
use Fortuneglobe\IceHawk\Test\Unit\Mocks\PhpStreamMock;

class DomainCommandTest extends \PHPUnit_Framework_TestCase
{
	public function testCanAccessValuesFromRequest()
	{
		$postData    = [ 'testValue' => 'Unit-Test' ];
		$postRequest = new PostRequest( $postData, [ ] );

		$command = new TestCommand( $postRequest );

		$this->assertEquals( 'Unit-Test', $command->getTestValue() );
		$this->assertEquals( $postData, $command->getTestData() );
	}

	public function testCanAccessUploadedFilesFromRequest()
	{
		$uploadedFilesArray = [
			'testFiles' => [
				'name'     => [ 'test1.file', 'test2.file' ],
				'tmp_name' => [ '/tmp/test1.file', '/tmp/test2.file' ],
				'type'     => [ 'text/plain', 'text/plain' ],
				'size'     => [ 1024, 2048 ],
				'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_OK ],
			],
			'fileTests' => [
				'name'     => [ 'test3.file', 'test4.file' ],
				'tmp_name' => [ '/tmp/test3.file', '/tmp/test4.file' ],
				'type'     => [ 'text/plain', 'text/plain' ],
				'size'     => [ 3072, 4096 ],
				'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_OK ],
			]
		];

		$expectedUploadedFiles = [
			'testFiles' => [
				new UploadedFileInfo( 'test1.file', '/tmp/test1.file', 'text/plain', 1024, UPLOAD_ERR_OK ),
				new UploadedFileInfo( 'test2.file', '/tmp/test2.file', 'text/plain', 2048, UPLOAD_ERR_OK ),
			],
			'fileTests' => [
				new UploadedFileInfo( 'test3.file', '/tmp/test3.file', 'text/plain', 3072, UPLOAD_ERR_OK ),
				new UploadedFileInfo( 'test4.file', '/tmp/test4.file', 'text/plain', 4096, UPLOAD_ERR_OK ),
			],
		];

		$postRequest = new PostRequest( [ ], $uploadedFilesArray );

		$command = new TestCommand( $postRequest );

		$this->assertEquals( $expectedUploadedFiles, $command->getAllTestFiles() );
		$this->assertEquals( $expectedUploadedFiles['testFiles'], $command->getTestFiles() );
		$this->assertEquals( $expectedUploadedFiles['testFiles'][1], $command->getTestFile() );
	}

	public function testCanAccessRawDataFromRequest()
	{
		stream_wrapper_unregister( "php" );
		stream_wrapper_register( "php", PhpStreamMock::class );
		file_put_contents( 'php://input', 'Unit-Test' );

		$postRequest = new PostRequest( [ ], [ ] );
		$command     = new TestCommand( $postRequest );

		$this->assertEquals( 'Unit-Test', $command->getBody() );

		stream_wrapper_restore( "php" );
	}
}