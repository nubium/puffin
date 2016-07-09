<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Module;
use Codeception\TestInterface;
use Exception;

/**
 * Class PuffinReporter
 * @package Codeception\Module
 */
class PuffinReporter extends Module
{
	private $failed = [];
	private $logFile;
	private $templateVars = [];
	private $templateFile;

	private $referenceImageDir;


	public function _initialize()
	{
		$this->debug('Initializing PuffinReport');

		if (array_key_exists('logFile', $this->config)) {
			$this->logFile = $this->config['logFile'];
		} else {
			$this->logFile = Configuration::logDir() . 'vcresult.html';
		}

		if (array_key_exists('templateVars', $this->config)) {
			$this->templateVars = $this->config['templateVars'];
		}

		if (array_key_exists('templateFile', $this->config)) {
			$this->templateFile = $this->config['templateFile'];
		} else {
			$this->templateFile = __DIR__ . '/report/template.php';
		}
	}


	/**
	 * @param array $settings
	 * @throws Exception
	 * @throws \Codeception\Exception\ModuleException
	 */
	public function _beforeSuite($settings = [])
	{
		if (!$this->hasModule('Puffin')) {
			throw new Exception('PuffinReporter uses Puffin. Please be sure that this module is activated.');
		}

		$this->referenceImageDir = $this->getModule('Puffin')->getReferenceScreenshotDirectory();

		$this->debug('PuffinReporter: templateFile = ' . $this->templateFile);
	}


	/**
	 * Generates template
	 */
	public function _afterSuite()
	{
		$failedTests = $this->failed;
		$vars = $this->templateVars;
		$referenceImageDir = $this->referenceImageDir;
		$i = 0;

		ob_start();
		include_once $this->templateFile;
		$reportContent = ob_get_contents();
		ob_clean();

		$this->debug('Trying to store file (' . $this->logFile . ')');
		file_put_contents($this->logFile, $reportContent);
	}


	/**
	 * @param TestInterface $test
	 * @param $fail
	 */
	public function _failed(TestInterface $test, $fail)
	{
		if ($fail instanceof ImageDeviationException) {
			$this->failed[] = $fail;
		}
	}
}
