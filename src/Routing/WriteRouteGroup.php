<?php
namespace Fortuneglobe\IceHawk\Routing;

use Fortuneglobe\IceHawk\Interfaces\HandlesWriteRequest;
use Fortuneglobe\IceHawk\Routing\Interfaces\ProvidesDestinationInfo;
use Fortuneglobe\IceHawk\Routing\Interfaces\RoutesToWriteHandler;
use Fortuneglobe\IceHawk\Routing\Interfaces\ProvidesMatchResult;
use Fortuneglobe\IceHawk\Routing\Patterns\NamedRegExp;

/**
 * Class WriteRouteGroup
 *
 * @package Fortuneglobe\IceHawk\Routing
 */
final class WriteRouteGroup implements RoutesToWriteHandler
{
	/** @var NamedRegExp */
	private $pattern;

	/** @var HandlesWriteRequest */
	private $requestHandler;

	/**
	 * @var array
	 */
	private $routes;

	/**
	 * @var array
	 */
	private $uriParams = [ ];

	public function __construct( ProvidesMatchResult $pattern, array $routes = [ ] )
	{
		$this->pattern        = $pattern;
		$this->routes         = $routes;
	}

	public function addRoute( RoutesToWriteHandler $route ) : self
	{
		$this->routes[] = $route;

		return $this;
	}

	public function matches( ProvidesDestinationInfo $destinationInfo ) : bool
	{
		if ( $this->pattern->matches( $destinationInfo->getUri() ) )
		{
			foreach ( $this->routes as $route )
			{
				if ( $route->matches( $destinationInfo ) )
				{
					$this->requestHandler = $route->getRequestHandler();
					$this->uriParams      = $route->getUriParams();

					return true;
				}
			}
		}

		return false;
	}

	public function getUriParams() : array
	{
		return $this->uriParams;
	}

	public function getRequestHandler()
	{
		return $this->requestHandler;
	}
}