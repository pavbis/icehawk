<?php
/**
 *
 * @author hollodotme
 */

namespace Fortuneglobe\IceHawk\DomainRequestHandlers;

use Fortuneglobe\IceHawk\Api;
use Fortuneglobe\IceHawk\DomainRequestHandler;
use Fortuneglobe\IceHawk\Exceptions\InvalidRequestType;
use Fortuneglobe\IceHawk\Interfaces\ServesRequestData;
use Fortuneglobe\IceHawk\Requests\PostRequest;
use Fortuneglobe\IceHawk\Responses\Forbidden;

/**
 * Class CommandHandler
 *
 * @package Fortuneglobe\IceHawk\DomainRequestHandlers
 */
final class CommandHandler extends DomainRequestHandler
{

	/**
	 * @param ServesRequestData $request
	 *
	 * @throws InvalidRequestType
	 * @throws InvalidDomainCommand
	 */
	public function handleRequest( ServesRequestData $request )
	{
		$this->guardRequestType( $request );

		$builder = new CommandBuilder( $this->api, $this->domain, $this->command, $this->project_namespace );
		$command = $builder->buildCommand( $request );

		if ( $this->isExecutable( $command ) )
		{
			if ( $command->isValid() )
			{
				$command->execute();
			}
			else
			{
				$command->getResponder()->addBadRequest( $command->getValidationMessages() );
			}
		}
		else
		{
			$command->getResponder()->add( Api::ALL, new Forbidden() );
		}

		$command->getResponder()->respond();
	}

	/**
	 * @param ServesRequestData $request
	 *
	 * @throws InvalidRequestType
	 */
	private function guardRequestType( ServesRequestData $request )
	{
		if ( !($request instanceof PostRequest) )
		{
			throw new InvalidRequestType( get_class( $request ) );
		}
	}
}