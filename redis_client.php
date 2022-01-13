<?php
/*
 * This file contains complete Redis client with:
 * Redis_Exception
 * Redis_Connection
 * Redis_Client
 *
 * Only simple commands are supported (no lists, no hashes).
 * This file is all you need (no autoloader loading external files)
 *
 * Based on: https://github.com/yampee/Redis/
 * Copyright (c) 2013 Titouan Galopin
 */


class Redis_Exception extends Exception
{
	protected $type;


	public function __construct($type, $msg='')
	{
		$this->type = $type;
		$this->message = sprintf("Redis-Exception (Type=%s): %s" , $type, $msg);
	}


	public function getType()
	{
		return $this->type;
	}
}// Redis_Exception



class Redis_Connection
{
	protected $socket;


	public function __construct($host = 'localhost', $port = 6379)
	{
		$socket = fsockopen($host, $port, $errno, $errstr);
		if (! $socket) {
			$msg = sprintf("Unable to connect to Redis at [%s:%s]", $host, $port);
			throw new Redis_Exception('connection', $msg);
		}
		$this->socket = $socket;
	}


	public function getSocket()
	{
		return $this->socket;
	}


	public function send($command)
	{
		return fwrite($this->socket, $command);
	}


	public function read()
	{
		return fgets($this->socket);
	}


	public function positionRead($position)
	{
		return fread($this->socket, $position);
	}
}// Redis_Connection



class Redis_Client
{
	protected $connection;
	protected $host = 'localhost';
	protected $port = 6379;

	public function __construct($host = 'localhost', $port = 6379)
	{
		$this->host = $host;
		$this->port = $port;
		$this->connect();
	}


	// for all not implemented commands use "send" command
	public function send($command, array $arguments = array())
	{
		return $this->execute(array_merge(array($command), $arguments));
	}


	public function has($key)
	{
		return (boolean) $this->send('exists', array($key));
	}


	public function get($key)
	{
		if (! $this->has($key)) {
			$msg = sprintf('Key "%s" not found in Redis database.', $key);
			throw new Redis_Exception('error', $msg);
		}
		return $this->send('get', array($key));
	}


	public function set($key, $value, $expire = null)
	{
		if (is_int($expire)) {
			return $this->send('setex', array($key, $expire, $value));
		} else {
			return $this->send('set', array($key, $value));
		}
	}


	public function del($key)
	{
		return $this->send('del', array($key));
	}


	public function authenticate($password)
	{
		return $this->send('auth', array($password));
	}


	public function persist($key)
	{
		return $this->send('persist', array($key));
	}


	public function findKeys($pattern = '*')
	{
		return $this->send('keys', array($pattern));
	}


	public function flush()
	{
		return $this->send('flushdb');
	}


	public function getStats()
	{
		return $this->send('info');
	}


	public function getParameter($parameterName)
	{
		return $this->send('config', array('GET', $parameterName));
	}


	public function setParameter($parameterName, $value)
	{
		return $this->send('config', array('SET', $parameterName, $value));
	}


	public function getSize()
	{
		return $this->send('dbsize');
	}


	protected function connect()
	{
		$this->connection = new Redis_Connection($this->host, $this->port);
		return $this;
	}


	protected function execute(array $arguments)
	{
		if (! $this->connection) {
			$this->connect();
		}
		$command = '*'.count($arguments)."\r\n";
		foreach ($arguments as $argument) {
			$command .= '$'.strlen($argument)."\r\n".$argument."\r\n";
		}
		if (! $this->connection->send($command)) {
			$this->connect();
			if (! $this->connection->send($command)) {
				throw new Redis_Exception('command', $command);
			}
		}
		return $this->response($command);
	}


	protected function response($command)
	{
		$reply = $this->connection->read();
		if ($reply === false) {
			$this->connect();
			$reply = $this->connection->read();
			if ($reply === false) {
				throw new Redis_Exception('response', $command);
			}
		}
		$reply = trim($reply);
		switch ($reply[0]) {
			// An error occured
			case '-':
				throw new Redis_Exception('error', $reply);
				break;
			// Inline response
			case '+':
				return substr($reply, 1);
				break;
			// Bulk response
			case '$':
				$response = null;
				if ($reply == '$-1') {
					return false;
					break;
				}
				$size = intval(substr($reply, 1));
				if ($size > 0) {
					$response = stream_get_contents($this->connection->getSocket(), $size);
				}
				// Discard crlf
				$this->connection->positionRead(2);
				return $response;
				break;
			// Multi-bulk response
			case '*':
				$count = substr($reply, 1);
				if ($count == '-1') {
					return null;
				}
				$response = array();
				for ($i = 0; $i < $count; $i++) {
					$response[] = $this->response($command);
				}
				return $response;
				break;
			// Integer response
			case ':':
				return intval(substr($reply, 1));
				break;
			// Error: not supported
			default:
				throw new Redis_Exception('error', 'Non-protocol answer: '.print_r($reply, 1));
		}
	}
}// Redis_Client
