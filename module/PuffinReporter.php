<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Module;
use Codeception\TestInterface;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class PuffinReporter
 * @package Codeception\Module
 */
class PuffinReporter extends Module
{
	/**
	 * @var string working directory
	 */
	private $workingDirectory;



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
	 * @throws RuntimeException
	 */
	public function _beforeSuite($settings = [])
	{
		if (!$this->hasModule('Puffin')) {
			throw new RuntimeException('PuffinReporter uses Puffin. Please be sure that this module is activated.');
		}

		/** @var Puffin $puffin */
		$puffin = $this->getModule('Puffin');
		$this->workingDirectory = $puffin->getWorkingDirectory();

		$this->debug('Working directory has been set to ' . $this->workingDirectory);
	}


	/**
	 * Generates template
	 */
	public function _afterSuite()
	{
		// find all json files in working directory
		$finder = new Finder;
		$resultFiles = $finder->files()->name('*.json');

		// decode its json content
		$results = [];
		/** @var SplFileInfo $file */
		foreach ($resultFiles->in($this->workingDirectory) as $file) {
			$results[] = json_decode($file->getContents(), true);
		}

		// sort results by deviation
		uasort($results, function ($left, $right) {
			if ($left['deviation'] === $right['deviation']) {
				return 0;
			}
			return $left['deviation'] < $right['deviation'] ? 1 : -1;
		});

		// convert images to base64 string
		foreach ($results as $key => $result) {
			$results[$key]['productionImage'] = $this->imageToBase64($results[$key]['productionImagePath']);
			$results[$key]['stagingImage'] = $this->imageToBase64($results[$key]['stagingImagePath']);
			$results[$key]['comparisonImage'] = $this->imageToBase64($results[$key]['comparisonImagePath']);
		}

		// render report
		ob_start();
		include __DIR__ . '/report/report.php';
		$report = ob_get_clean();

		file_put_contents($this->workingDirectory . '/report.html', $report);
	}


	/**
	 * @param string $path to image
	 * @return string base64 encoded image ready to be placed in image src attribute
	 */
	private function imageToBase64($path)
	{
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$data = file_get_contents($path);
		return 'data:image/' . $type . ';base64,' . base64_encode($data);
	}
}
