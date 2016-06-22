<?php
/**
 * @author h.woltersdorf
 */

namespace Fortuneglobe\IceHawk\Tests\Unit\RequestHandlers;

use Fortuneglobe\IceHawk\Defaults\RequestInfo;
use Fortuneglobe\IceHawk\Interfaces\ConfiguresIceHawk;
use Fortuneglobe\IceHawk\Interfaces\HandlesPostRequest;
use Fortuneglobe\IceHawk\Interfaces\RespondsFinallyToWriteRequest;
use Fortuneglobe\IceHawk\PubSub\EventPublisher;
use Fortuneglobe\IceHawk\RequestHandlers\WriteRequestHandler;
use Fortuneglobe\IceHawk\Routing\Patterns\Literal;
use Fortuneglobe\IceHawk\Routing\Patterns\RegExp;
use Fortuneglobe\IceHawk\Routing\WriteRoute;
use Fortuneglobe\IceHawk\Tests\Unit\Fixtures\Domain\Write\BodyDataRequestHandler;
use Fortuneglobe\IceHawk\Tests\Unit\Fixtures\Domain\Write\RequestParamsRequestHandler;
use Fortuneglobe\IceHawk\Tests\Unit\Mocks\PhpStreamMock;

class WriteRequestHandlerTest extends \PHPUnit_Framework_TestCase
{
	public function parameterProvider()
	{
		return [
			[
				[ 'unit' => 'test', 'test' => 'unit' ],
				'unit', 'tested',
				json_encode( [ 'unit' => 'tested', 'test' => 'unit' ] ),
			],
			[
				[ 'unit' => 'test', 'test' => 'unit' ],
				'test', 'units',
				json_encode( [ 'unit' => 'test', 'test' => 'units' ] ),
			],
			[
				[ 'unit' => [ 'test' => 'unit' ] ],
				'unit', 'units',
				json_encode( [ 'unit' => 'units' ] ),
			],
		];
	}

	/**
	 * @dataProvider parameterProvider
	 * @runInSeparateProcess
	 */
	public function testUriParamsOverwritesPostParams( array $postData, string $uriKey, string $uriValue, $expectedJson
	)
	{
		$_POST = $postData;

		$config      = $this->getMockBuilder( ConfiguresIceHawk::class )->getMockForAbstractClass();
		$requestInfo = new RequestInfo(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => sprintf( '/domain/test_request_param/%s/%s', $uriKey, $uriValue ),
			]
		);

		$regExp     = new RegExp(
			sprintf( '#^/domain/test_request_param/%s/(%s)$#', $uriKey, $uriValue ), [ $uriKey ]
		);
		$writeRoute = new WriteRoute( $regExp, new RequestParamsRequestHandler() );

		$config->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getWriteRoutes' )->willReturn( [ $writeRoute ] );

		$writeRequestHandler = new WriteRequestHandler( $config, new EventPublisher() );
		$writeRequestHandler->handleRequest();

		$this->expectOutputString( $expectedJson );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCanGetBodyDataFromInputStream()
	{
		stream_wrapper_unregister( "php" );
		stream_wrapper_register( "php", PhpStreamMock::class );
		file_put_contents( 'php://input', 'body data' );

		$config      = $this->getMockBuilder( ConfiguresIceHawk::class )->getMockForAbstractClass();
		$requestInfo = new RequestInfo( [ 'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/domain/test_body_data' ] );

		$writeRoute = new WriteRoute( new Literal( '/domain/test_body_data' ), new BodyDataRequestHandler() );

		$config->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getWriteRoutes' )->willReturn( [ $writeRoute ] );

		$writeRequestHandler = new WriteRequestHandler( $config, new EventPublisher() );
		$writeRequestHandler->handleRequest();

		$this->expectOutputString( 'body data' );

		stream_wrapper_restore( "php" );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testMissingWriteRoutesHandledByFinaleWriteResponder()
	{
		$requestInfo = new RequestInfo( [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test' ] );

		$finalWriteResponder = $this->getMockBuilder( RespondsFinallyToWriteRequest::class )->getMockForAbstractClass();
		$finalWriteResponder->method( 'handleUncaughtException' )
		                    ->will(
			                    $this->returnCallback(
				                    function ()
				                    {
					                    echo 'fine';
				                    }
			                    )
		                    );

		$config = $this->getMockBuilder( ConfiguresIceHawk::class )->getMockForAbstractClass();

		$config->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->expects( $this->once() )->method( 'getWriteRoutes' )->willReturn( [ ] );
		$config->method( 'getFinalWriteResponder' )->willReturn( $finalWriteResponder );

		$writeRequestHandler = new WriteRequestHandler( $config, new EventPublisher() );
		$writeRequestHandler->handleRequest();

		$this->expectOutputString( 'fine' );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testExceptionHandledByFinaleWriteResponder()
	{
		$requestInfo = new RequestInfo( [ 'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test' ] );
		$exception   = new \Exception();

		$finalWriteResponder = $this->getMockBuilder( RespondsFinallyToWriteRequest::class )->getMockForAbstractClass();
		$finalWriteResponder->method( 'handleUncaughtException' )
		                    ->will(
			                    $this->returnCallback(
				                    function ( $exception )
				                    {
					                    echo get_class( $exception );
				                    }
			                    )
		                    );

		$config = $this->getMockBuilder( ConfiguresIceHawk::class )->getMockForAbstractClass();
		
		$config->method( 'getRequestInfo' )->willReturn( $requestInfo );
		$config->method( 'getFinalWriteResponder' )->willReturn( $finalWriteResponder );

		$requestHandler = $this->getMockBuilder( HandlesPostRequest::class )->getMockForAbstractClass();
		$requestHandler->expects( $this->once() )
		               ->method( 'handle' )
		               ->will( $this->throwException( $exception ) );

		$writeRoute = new WriteRoute( new Literal( '/test' ), $requestHandler );

		$config->expects( $this->once() )->method( 'getWriteRoutes' )->willReturn( [ $writeRoute ] );

		$writeRequestHandler = new WriteRequestHandler( $config, new EventPublisher() );
		$writeRequestHandler->handleRequest();

		$this->expectOutputString( get_class( $exception ) );
	}
}