<?php

/**
 * The MIT License (MIT)
 *
 * Copyright 2023 alvin0319
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

declare(strict_types=1);

namespace alvin0319\VotifierAPI\thread;

use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\Thread;
use pmmp\thread\ThreadSafe;
use pmmp\thread\ThreadSafeArray;
use function fread;
use function fsockopen;
use function fwrite;
use function igbinary_serialize;
use function json_encode;
use function microtime;
use function socket_last_error;
use function socket_strerror;
use function stream_set_blocking;
use function time_sleep_until;
use function trim;
use const SOCKET_EWOULDBLOCK;

final class VoteThread extends Thread{

	private bool $shutdown = false;

	public const LOGIN_REQUEST = 0;
	public const LOGIN_SUCCESS = 1;
	public const LOGIN_FAILURE = 2;
	public const MESSAGE = 3;

	private bool $authenticated = false;

	public function __construct(
		private string $hostAddress,
		private int $hostPort,
		private string $password,
		private SleeperHandlerEntry $notifier,
		private ThreadSafeArray $in,
		private ThreadSafeArray $out
	){
	}

	protected function onRun() : void{
		try{
			$socket = fsockopen($this->hostAddress, $this->hostPort, $errno, $errstr, 5);
		}catch(\Throwable $e){
			return;
		}
		if(!$socket){
			echo "Failed to connect to server: $errstr ($errno)\n";
			$this->shutdown();
			return;
		}

		stream_set_blocking($socket, false);
		$opCode = json_encode([
			"op" => self::LOGIN_REQUEST,
			"payload" => $this->password
		]);

		if(fwrite($socket, $opCode . "\n") === false){
			throw new \RuntimeException("Failed to send auth data");
		}

        $notifier = $this->notifier->createNotifier();

		while(!$this->shutdown){
			$start = microtime(true);
			$res = fread($socket, 1024);
			if($res === false){
				$errno = socket_last_error($socket);
				if($errno === SOCKET_EWOULDBLOCK){
					continue;
				}
				throw new \RuntimeException("Socket error: " . socket_strerror($errno));
			}
			if(trim($res) !== ""){
				$this->out[] = igbinary_serialize($res);
				$notifier->wakeupSleeper();
			}
			while(($data = $this->in->shift()) !== null){
				$opCode = json_encode([
					"op" => self::MESSAGE,
					"payload" => $data
				]);
				if(fwrite($socket, $opCode . "\n") === false){
					throw new \RuntimeException("Failed to send data");
				}
			}
			$end = microtime(true);
			if(($diff = $end - $start) < 0.02){
				time_sleep_until($end + 0.025 - $diff);
			}
		}
		fclose($socket);
	}

	public function shutdown() : void{
		$this->synchronized(function() : void{
			$this->shutdown = true;
			$this->notify();
		});
	}

	public function setAuthenticated(bool $authenticated) : void{
		$this->synchronized(function() use ($authenticated) : void{
			$this->authenticated = $authenticated;
			$this->notify();
		});
	}
}