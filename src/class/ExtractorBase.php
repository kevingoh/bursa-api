<?php

abstract class ExtractorBase {
	
	abstract public function build_url();
	
	abstract public function process_raw_contents($raw_contents);
	
	public function retrieve_raw_contents($url)
	{
		$client = new GuzzleHttp\Client();
		$result = $client->request('GET', $url);
		
		if($result->getStatusCode() != 200){
			throw new Exception('HTTP Request with status code ' . $result->getStatusCode());
		}
		
		return $result->getBody();
	}
	
	public function run()
	{
		$url = $this->build_url();
		$raw_contents = $this->retrieve_raw_contents($url);
		return $this->process_raw_contents($raw_contents);
	}
}