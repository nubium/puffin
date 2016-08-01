<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Exception\ElementNotFound;
use Codeception\Module;
use Codeception\TestCase;
use Facebook\WebDriver\Exception\InvalidElementStateException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverElement;
use Imagick;
use ImagickException;
use WebDriverBy;

/**
 * Class Puffin
 * @package Codeception\Module
 */
class Puffin extends Module
{
	/**
	 * @const screenshot extension
	 */
	const SCREENSHOT_EXTENSION = 'png';

	/**
	 * @const comparison result extension
	 */
	const COMPARISON_RESULT_EXTENSION = 'json';

	/**
	 * @var string
	 */
	private $workingDirectory;

	/**
	 * @var TestCase
	 */
	private $test;

	/**
	 * Maximum deviation for screenshots comparison
	 * @var int
	 */
	private $maximumDeviation = 0;

	/**
	 * @var RemoteWebDriver
	 */
	private $webDriver;

	/**
	 * @var WebDriver
	 */
	private $webDriverModule;


	/**
	 * Initialize the module and read the config.
	 * Throws a runtime exception, if the
	 * reference image dir is not set in the config
	 *
	 * @throws \RuntimeException
	 * @throws InvalidElementStateException
	 */
	public function _initialize()
	{
		if (array_key_exists('maximumDeviation', $this->config)) {
			$this->maximumDeviation = $this->config['maximumDeviation'];
		}

		if (array_key_exists('workingDirectory', $this->config)) {
			$this->workingDirectory = $this->config['workingDirectory'];
		} else {
			$this->workingDirectory = Configuration::outputDir() . '/Puffin/';
		}

		if (!@mkdir($this->workingDirectory, 0777, true) && !is_dir($this->workingDirectory)) {
			throw new InvalidElementStateException('Unable to create working directory');
		}
	}


	/**
	 * Event hook before a test starts
	 *
	 * @param TestCase $test
	 * @throws InvalidElementStateException
	 */
	public function _before(TestCase $test)
	{
		$webDriverModule = null;
		foreach ($this->getModules() as $module) {
			if ($module instanceof WebDriver) {
				$webDriverModule = $module;
			}
		}
		if (!$webDriverModule) {
			throw new InvalidElementStateException('Puffin uses the WebDriver. Please be sure that this module is activated.');
		}

		$this->webDriverModule = $webDriverModule;
		$this->webDriver = $this->webDriverModule->webDriver;

		$this->test = $test;
	}


	/**
	 * Compare the reference image with a current screenshot, identified by their indentifier name
	 * and their element ID.
	 *
	 * @param string $identifier identifies your test object
	 * @param string $elementID DOM ID of the element, which should be screenshotted
	 * @throws \Exception
	 */
	public function dontSeeVisualChanges($identifier, $elementID = 'body')
	{
		$environment = $this->test->getScenario()->current('env');
		if ($environment) {
			$identifier = $identifier . '.' . $environment;
		}

		// used for assertion counter in codeception / phpunit
		$this->assertTrue(true);

		$comparisonResult = $this->compareScreenshots($identifier, $elementID);

		$result = [
			'identifier' => $identifier,
			'deviation' => $comparisonResult['deviation'],
			'productionImagePath' => $this->getProductionScreenshotPath($identifier),
			'stagingImagePath' => $this->getStagingScreenshotPath($identifier),
			'comparisonImagePath' => null,
			'workingDirectory' => $this->getWorkingDirectory(),
		];


		// if comparison has been done 
		if (
			$comparisonResult['deviationImage'] instanceof Imagick
		) {
			$compareScreenshotPath = $this->getComparisonScreenshotPath($identifier);
			if ($comparisonResult['deviationImage']->writeImage($compareScreenshotPath)) {
				$result['comparisonImagePath'] = $compareScreenshotPath;
			} else {
				throw new \RuntimeException('Failed to write composed image.');
			}

			$this->debug('See ' . $compareScreenshotPath . ' for a deviation screenshot.');
		}

		$this->saveComparisonResult($result);
	}


	/**
	 * @param array $result
	 * @throws \Exception
	 */
	private function saveComparisonResult(array $result)
	{

		if (!array_key_exists('identifier', $result)) {
			throw new \InvalidArgumentException('Result must contain identifier of test object.');
		}

		if (!array_key_exists('deviation', $result)) {
			throw new \InvalidArgumentException('Result must contain deviation information.');
		}

		if ($result['deviation'] === 0) {
			return;
		}

		if (!array_key_exists('productionImagePath', $result)) {
			throw new \InvalidArgumentException('Result must contain information about production image path.');
		}

		if (!array_key_exists('stagingImagePath', $result)) {
			throw new \InvalidArgumentException('Result must contain information about staging image path.');
		}

		if (!array_key_exists('comparisonImagePath', $result)) {
			throw new \InvalidArgumentException('Result must contain information about comparison image path.');
		}

		$comparisonResultPath = $this->getComparisonResultPath($result['identifier']);
		if (!file_put_contents($comparisonResultPath, json_encode($result, JSON_PRETTY_PRINT))) {
			throw new \Exception('Failed to write comparison result to ' . $comparisonResultPath);
		}
	}


	/**
	 * Compares the two images and calculate the deviation between expected and actual image
	 *
	 * @param string $identifier Identifies your test object
	 * @param string $selector DOM ID of the element, which should be screenshotted
	 * @return array Includes the calculation of comparison result and the diff-image
	 * @throws \Codeception\Exception\ElementNotFound
	 */
	private function compareScreenshots($identifier, $selector)
	{
		$coordinates = $this->getCoordinates($selector);
		$this->takeScreenshot($identifier, $coordinates);

		$compareResult = $this->compare($identifier);

		if ($compareResult['composedImages'] instanceof Imagick) {
			$this->debug('Comparison result is ' . $this->formatNumber($compareResult['difference']));
		} else {
			$this->debug('Production image not found, this is first run and comparision cannot be done.');
		}

		return [
			'deviation' => $compareResult['difference'],
			'deviationImage' => $compareResult['composedImages'],
			'comparisonImage' => $compareResult['comparisonImage'],
		];
	}


	/**
	 * Find the position and proportion of a DOM element, specified by it's ID.
	 * The method inject the
	 * JQuery Framework and uses the "noConflict"-mode to get the width, height and offset params.
	 *
	 * @param string $selector DOM ID/class of the element, which should be screenshotted
	 * @return array coordinates of the element
	 * @throws \Codeception\Exception\ElementNotFound
	 */
	private function getCoordinates($selector = 'body')
	{
		try {
			$this->webDriverModule->waitForElementVisible($selector, 10);

			/** @var WebDriverElement|null $element */
			$element = $this->webDriver->findElement(WebDriverBy::cssSelector($selector));
		} catch (\Exception $e) {
			throw new ElementNotFound('Element ' . $selector . ' could not be located by WebDriver');
		}

		$elementSize = $element->getSize();
		$elementLocation = $element->getLocation();
		$imageCoords['x'] = $elementLocation->getX();
		$imageCoords['y'] = $elementLocation->getY();
		$imageCoords['width'] = $elementSize->getWidth();
		$imageCoords['height'] = $elementSize->getHeight();

		return $imageCoords;
	}


	/**
	 * Generates a screenshot image filename
	 * it uses the testcase name and the given indentifier to generate a png image name
	 *
	 * @param string $identifier identifies your test object
	 * @return string Name of the image file
	 */
	private function getScreenshotName($identifier)
	{
		$className = preg_replace('/(Cept|Cest)\.php/', '', basename($this->test->getFileName()));

		return $className . '.' . $identifier;
	}


	/**
	 * Generate the screenshot of the dom element
	 *
	 * @param string $identifier identifies your test object
	 * @param array $coordinates Coordinates where the DOM element is located
	 * @return string Path of the current screenshot image
	 * @throws \RuntimeException
	 */
	private function takeScreenshot($identifier, array $coordinates)
	{
		$temporaryPath = $this->workingDirectory . 'fullscreenshot.tmp.' . self::SCREENSHOT_EXTENSION;
		$screenshotPath = $this->getScreenshotPath($identifier);

		$this->webDriver->takeScreenshot($temporaryPath);

		$screenshot = new Imagick($temporaryPath);
		$screenshot->cropImage($coordinates['width'], $coordinates['height'], $coordinates['x'], $coordinates['y']);
		$screenshot->writeImage($screenshotPath);

		unlink($temporaryPath);

		return $screenshotPath;
	}


	/**
	 * Compare two images by its identifiers.
	 * If the reference image doesn't exists
	 * the image is copied to the reference path.
	 *
	 * @param string $identifier identifies your test object
	 * @return array Test result of image comparison
	 * @throws \RuntimeException
	 */
	private function compare($identifier)
	{
		$productionScreenshotPath = $this->getProductionScreenshotPath($identifier);
		$stagingScreenshotPath = $this->getStagingScreenshotPath($identifier);

		if (file_exists($productionScreenshotPath) && file_exists($stagingScreenshotPath)) {
			$this->debug('Comparing ' . $productionScreenshotPath . ' and ' . $stagingScreenshotPath);
			return $this->compareImages($productionScreenshotPath, $stagingScreenshotPath);
		} else {
			return [
				'composedImages' => null,
				'comparisonImage' => null,
				'difference' => 0,
			];
		}
	}


	/**
	 * Compares to images by given file path
	 *
	 * @param string $referenceImagePath Path to the exprected reference image
	 * @param string $comparisonImagePath Path to the current image in the screenshot
	 * @return array Result of the comparison
	 */
	private function compareImages($referenceImagePath, $comparisonImagePath)
	{
		$referenceImage = new Imagick($referenceImagePath);
		$comparisonImage = new Imagick($comparisonImagePath);

		$this->debug('Determining maximum width and height of given screenshots');
		$maximumWidth = max($referenceImage->getImageWidth(), $comparisonImage->getImageWidth());
		$maximumHeight = max($referenceImage->getImageHeight(), $comparisonImage->getImageHeight());

		$this->debug('Extending screenshots to the same size - ' . $maximumWidth . 'x' . $maximumHeight);
		$referenceImage->extentImage($maximumWidth, $maximumHeight, 0, 0);
		$comparisonImage->extentImage($maximumWidth, $maximumHeight, 0, 0);


		$comparisonResult = [];
		try {
			$result = $referenceImage->compareImages($comparisonImage, Imagick::METRIC_MEANSQUAREERROR);
			/** @var $comparisonResult ['composedImages'] Imagick */
			$comparisonResult['composedImages'] = $result[0];
			$comparisonResult['composedImages']->setImageFormat('png');

			$comparisonResult['comparisonImage'] = clone $comparisonImage;
			$comparisonResult['comparisonImage']->setImageFormat('png');

			$comparisonResult['difference'] = $result[1];
		} catch (ImagickException $e) {
			$this->fail(
				'Could not compare images: '
				. $e->getMessage()
				. PHP_EOL
				. $referenceImagePath
				. PHP_EOL
				. $comparisonImagePath
			);
		}

		return $comparisonResult;
	}


	public function getWorkingDirectory()
	{
		return $this->workingDirectory;
	}


	/**
	 * Returns the temporary path including the filename where a the screenshot should be saved
	 * If the path doesn't exist, the method generate it itself
	 *
	 * @param string $identifier identifies your test object
	 * @return string Path an name of the image file
	 * @throws \RuntimeException if debug dir could not create
	 */
	private function getScreenshotPath($identifier)
	{
		$productionScreenshotPath = $this->getProductionScreenshotPath($identifier);
		$stagingScreenshotPath = $this->getStagingScreenshotPath($identifier);

		if (file_exists($productionScreenshotPath)) {
			return $stagingScreenshotPath;
		} else {
			return $productionScreenshotPath;
		}
	}


	/**
	 * Returns the production screenshot path including the filename
	 *
	 * @param string $identifier identifies your test object
	 * @return string Name of the production image file
	 */
	private function getProductionScreenshotPath($identifier)
	{
		return $this->getBasePath($identifier) . '.production.' . self::SCREENSHOT_EXTENSION;
	}


	/**
	 * Returns the production screenshot path including the filename
	 *
	 * @param string $identifier identifies your test object
	 * @return string Name of the staging image file
	 */
	private function getStagingScreenshotPath($identifier)
	{
		return $this->getBasePath($identifier) . '.staging.' . self::SCREENSHOT_EXTENSION;
	}


	/**
	 * Returns the production screenshot path including the filename
	 *
	 * @param string $identifier identifies your test object
	 * @return string Name of the comparison result
	 */
	private function getComparisonResultPath($identifier)
	{
		return $this->getBasePath($identifier) . '.comparison.' . self::COMPARISON_RESULT_EXTENSION;
	}


	/**
	 * Returns the image path including the filename of a deviation image
	 *
	 * @param string $identifier identifies your test object
	 * @return string Path of the composed image
	 */
	private function getComparisonScreenshotPath($identifier)
	{
		return $this->getBasePath($identifier) . '.comparison.' . self::SCREENSHOT_EXTENSION;
	}


	/**
	 * @param $identifier
	 * @return string
	 */
	private function getBasePath($identifier)
	{
		return $this->workingDirectory . $this->getScreenshotName($identifier);
	}


	/**
	 * @param int $number
	 * @return string
	 */
	private function formatNumber($number)
	{
		return number_format($number ?: 0, 15);
	}

}
