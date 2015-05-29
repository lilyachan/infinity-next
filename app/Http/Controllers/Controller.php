<?php namespace App\Http\Controllers;

use App\Board;
use App\Log;
use App\Option;
use App\Support\Anonymous;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;
use Illuminate\Foundation\Bus\DispatchesCommands;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Request;
use View;

abstract class Controller extends BaseController {
	
	use DispatchesCommands, ValidatesRequests;
	
	/**
	 * Cache of the system's options
	 *
	 * @var array
	 */
	protected $options;
	
	/**
	 * Create a new authentication controller instance.
	 *
	 * @param  \Illuminate\Auth\Guard  $auth
	 * @param  \Illuminate\Auth\Registrar  $registrar
	 * @return void
	 */
	public function __construct(Guard $auth, Registrar $registrar)
	{
		$this->auth      = $auth;
		$this->registrar = $registrar;
		
		if ($auth->guest())
		{
			$this->user  = new Anonymous;
		}
		else
		{
			$this->user  = $auth->user();
		}
		
		View::share('boardbar', Board::getBoardListBar());
		View::share('user', $this->user);
	}
	
	/**
	 * Logs an action.
	 *
	 * @param  string  $action
	 * @param  App\Board|String  $board
	 * @param  Array $data
	 * @return App\Log
	 */
	public function log($action, $board = null, $data = null)
	{
		$board_uri      = null;
		$action_details = null;
		
		if ($board instanceof \App\Board)
		{
			$board_uri      = $board->board_uri;
			$action_details = $data;
		}
		else if ($board instanceof \App\Post)
		{
			$board_uri      = $board->board_uri;
			$action_details = $data;
		}
		else if (is_string($board))
		{
			$board_uri      = $board;
			$action_details = $data;
		}
		else if(is_array($board) && is_null($data))
		{
			$board_uri      = null;
			$action_details = $board;
		}
		
		if (!is_null($action_details) && !is_array($action_details))
		{
			$action_details = [ $action_details ];
		}
		
		if (!is_null($action_details))
		{
			$action_details = json_encode( $action_details );
		}
		
		$log = new Log([
			'action_name'    => $action,
			'action_details' => $action_details,
			'user_id'        => $this->user->isAnonymous() ? null : $this->user->user_id,
			'user_ip'        => Request::getClientIp(),
			'board_uri'      => $board_uri,
		]);
		
		return $log->save();
	}
	
	
	/**
	 * Returns an system option's value.
	 *
	 * @param  string  $option
	 * @return string|null
	 */
	public function option($option_name)
	{
		if (!isset($this->options))
		{
			$this->options = Option::get();
		}
		
		foreach ($this->options as $option)
		{
			if ($option->option_name == $option_name)
			{
				return $option->option_value;
			}
		}
		
		return null;
	}
	
	/**
	 * Creates a View with the requested content file.
	 *
	 * @param  string  $template
	 * @param  array  $options
	 * @return View
	 */
	public function view($template, array $options = [])
	{
		return View::make(
			$this->template($template),
			$this->templateOptions($options)
		);
	}
	
	/**
	 * Modifies a template path to yield the correct result.
	 *
	 * @param  string  $template
	 * @return string
	 */
	public static function template($template)
	{
		return "content.{$template}";
	}
	
	/**
	 * Modifies template arguments to include required information.
	 *
	 * @param  array  $options
	 * @return array
	 */
	public function templateOptions(array $options = [])
	{
		return (array) array_merge([
			'c'          => &$this,
			'controller' => &$this,
		], $options);
	}
}