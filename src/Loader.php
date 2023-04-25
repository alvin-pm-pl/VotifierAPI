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

namespace alvin0319\VotifierAPI;

use alvin0319\VotifierAPI\event\PlayerVoteEvent;
use alvin0319\VotifierAPI\thread\VoteThread;
use pocketmine\plugin\PluginBase;
use pocketmine\snooze\SleeperNotifier;
use function igbinary_unserialize;
use function json_decode;

final class Loader extends PluginBase{

	private VoteThread $thread;

	private SleeperNotifier $notifier;

	protected function onEnable() : void{
		$this->saveDefaultConfig();

		$in = new \Volatile();
		$out = new \Volatile();

		$this->notifier = new SleeperNotifier();

		$this->thread = new VoteThread(
			$this->getConfig()->get("address"),
			$this->getConfig()->get("port"),
			$this->getConfig()->get("password"),
			$this->notifier,
			$in,
			$out
		);
		$this->thread->start();

		$this->getServer()->getTickSleeper()->addNotifier($this->notifier, function() use ($out) : void{
			if(($data = $out->shift()) !== null){
				$opData = json_decode(igbinary_unserialize($data), true);
				if($opData["op"] === VoteThread::LOGIN_SUCCESS){
					$this->thread->setAuthenticated(true);
					$this->getLogger()->info("Authenticated to Votifier server");
				}elseif($opData["op"] === VoteThread::LOGIN_FAILURE){
					$this->getLogger()->error("Failed to authenticate to Votifier server");
					$this->getServer()->getPluginManager()->disablePlugin($this);
				}elseif($opData["op"] === VoteThread::MESSAGE){
					$message = json_decode($opData["payload"], true);
					$serviceName = $message["serviceName"];
					$username = $message["username"];
					$address = $message["address"];
					$timestamp = $message["timestamp"];
					(new PlayerVoteEvent($username, $serviceName, $address, $timestamp))->call();
					$this->getLogger()->debug("Got vote from votifier server: $username, $serviceName, $address, $timestamp");
				}
			}
		});
	}

	protected function onDisable() : void{
		if(isset($this->thread)){
			$this->thread->shutdown();
		}
		if(isset($this->notifier)){
			$this->getServer()->getTickSleeper()->removeNotifier($this->notifier);
		}
	}
}