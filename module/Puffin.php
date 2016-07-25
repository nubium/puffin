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
	 * @var string
	 */
	private $referenceScreenshotDirectory;

	/**
	 * This var represents the directory where the taken images are stored
	 * @var string
	 */
	private $screenshotDirectory;

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
	private $remoteWebDriver;

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

		if (array_key_exists('referenceDirectory', $this->config)) {
			$this->referenceScreenshotDirectory = $this->config['referenceDirectory'];
		} else {
			$this->referenceScreenshotDirectory = Configuration::dataDir() . 'Puffin/';
		}

		if (!is_dir($this->referenceScreenshotDirectory) && !@mkdir($this->referenceScreenshotDirectory, 0777, true)) {
			throw new InvalidElementStateException('Unable to create screenshot directory');
		}

		if (array_key_exists('currentImageDir', $this->config)) {
			$this->screenshotDirectory = $this->config['currentImageDir'];
		} else {
			$this->screenshotDirectory = Configuration::logDir() . 'debug/tmp/';
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
		$this->remoteWebDriver = $this->webDriverModule->webDriver;

		$this->test = $test;
	}


	/**
	 * Compare the reference image with a current screenshot, identified by their indentifier name
	 * and their element ID.
	 *
	 * @param string $identifier Identifies your test object
	 * @param string $elementID DOM ID of the element, which should be screenshotted
	 * @throws \Codeception\Module\ImageDeviationException
	 * @throws \RuntimeException
	 */
	public function seeVisualChanges($identifier, $elementID = 'body')
	{
		$environment = $this->test->getScenario()->current('env');
		if ($environment) {
			$identifier = $identifier . '.' . $environment;
		}

		$deviationResult = $this->getScreenshotDeviation($identifier, $elementID);

		if ($deviationResult['deviationImage'] !== null) {

			// used for assertion counter in codeception / phpunit
			$this->assertTrue(true);

			if ($deviationResult['deviation'] <= $this->maximumDeviation) {
				$compareScreenshotPath = $this->getDeviationScreenshotPath($identifier);
				$deviationResult['deviationImage']->writeImage($compareScreenshotPath);

				throw new ImageDeviationException(
					'Comparison result is too low - ' . number_format(($deviationResult['difference'] ?: 0), 15) . '.'
					. PHP_EOL
					. 'See ' . $compareScreenshotPath . ' for a deviation screenshot.',
					$this->getReferenceScreenshotPath($identifier),
					$this->getScreenshotPath($identifier),
					$compareScreenshotPath
				);
			}
		}
	}


	/**
	 * Compare the reference image with a current screenshot, identified by their indentifier name
	 * and their element ID.
	 *
	 * @param string $identifier identifies your test object
	 * @param string $elementID DOM ID of the element, which should be screenshotted
	 * @throws \Codeception\Module\ImageDeviationException
	 * @throws \RuntimeException
	 */
	public function dontSeeVisualChanges($identifier, $elementID = 'body')
	{
		$environment = $this->test->getScenario()->current('env');
		if ($environment) {
			$identifier = $identifier . '.' . $environment;
		}

		$deviationResult = $this->getScreenshotDeviation($identifier, $elementID);

		if ($deviationResult['deviationImage'] instanceof Imagick) {

			// used for assertion counter in codeception / phpunit
			$this->assertTrue(true);

			if ($deviationResult['deviation'] > $this->maximumDeviation) {
				$compareScreenshotPath = $this->getDeviationScreenshotPath($identifier);
				$deviationResult['deviationImage']->writeImage($compareScreenshotPath);

				throw new ImageDeviationException(
					'Comparison result is too high - ' . $deviationResult['deviation'] . '.'
					. PHP_EOL
					. 'See ' . $compareScreenshotPath . ' for a deviation screenshot.',
					$this->getReferenceScreenshotPath($identifier),
					$this->getScreenshotPath($identifier),
					$compareScreenshotPath
				);
			}
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
	private function getScreenshotDeviation($identifier, $selector)
	{
		$coordinates = $this->getCoordinates($selector);
		$this->createScreenshot($identifier, $coordinates);

		$compareResult = $this->compare($identifier);

		if ($compareResult['composedImages'] instanceof Imagick) {
			$this->debug('Comparison result - ' . number_format(($compareResult['difference'] ?: 0), 15));
		} else {
			$this->debug('Reference image not found, this is first run and comparsion cannot be done.');
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
			$element = $this->remoteWebDriver->findElement(WebDriverBy::cssSelector($selector));
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

		return $className . '.' . $identifier . '.png';
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
		$debugDir = $this->screenshotDirectory;
		if (!is_dir($debugDir)) {
			$created = mkdir($debugDir, 0777, true);
			if ($created) {
				$this->debug("Creating directory: $debugDir}");
			} else {
				throw new \RuntimeException("Unable to create temporary screenshot dir ($debugDir)");
			}
		}
		return $debugDir . $this->getScreenshotName($identifier);
	}


	/**
	 * Returns the reference image path including the filename
	 *
	 * @param string $identifier identifies your test object
	 * @return string Name of the reference image file
	 */
	private function getReferenceScreenshotPath($identifier)
	{
		return $this->referenceScreenshotDirectory . $this->getScreenshotName($identifier);
	}


	/**
	 * Generate the screenshot of the dom element
	 *
	 * @param string $identifier identifies your test object
	 * @param array $coords Coordinates where the DOM element is located
	 * @return string Path of the current screenshot image
	 * @throws \RuntimeException
	 */
	private function createScreenshot($identifier, array $coords)
	{
		$screenShotDir = Configuration::logDir() . 'debug/';

		if (!is_dir($screenShotDir)) {
			mkdir($screenShotDir, 0777, true);
		}
		$screenshotPath = $screenShotDir . 'fullscreenshot.tmp.png';
		$elementPath = $this->getScreenshotPath($identifier);

		$this->remoteWebDriver->takeScreenshot($screenshotPath);

		$screenShotImage = new Imagick;
		$screenShotImage->readImage($screenshotPath);
		$screenShotImage->cropImage($coords['width'], $coords['height'], $coords['x'], $coords['y']);
		$screenShotImage->writeImage($elementPath);

		unlink($screenshotPath);

		return $elementPath;
	}


	/**
	 * Returns the image path including the filename of a deviation image
	 *
	 * @param string $identifier identifies your test object
	 * @param string $alternativePrefix
	 * @return string Path of the deviation image
	 */
	private function getDeviationScreenshotPath($identifier, $alternativePrefix = '')
	{
		$debugDir = Configuration::logDir() . 'debug/';
		$prefix = ($alternativePrefix === '') ? 'compare' : $alternativePrefix;
		return $debugDir . $prefix . $this->getScreenshotName($identifier);
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
		$referenceImagePath = $this->getReferenceScreenshotPath($identifier);
		$comparisonImagePath = $this->getScreenshotPath($identifier);

		if (!file_exists($referenceImagePath)) {
			$this->debug("Copying image (from $comparisonImagePath to $referenceImagePath");
			copy($comparisonImagePath, $referenceImagePath);

			return [
				'composedImages' => null,
				'comparisonImage' => null,
				'difference' => 0,
			];
		} else {
			$this->debug('Comparing ' . $referenceImagePath . ' and ' . $comparisonImagePath);
			return $this->compareImages($referenceImagePath, $comparisonImagePath);
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


	public function getReferenceScreenshotDirectory()
	{
		return $this->referenceScreenshotDirectory;
	}

}
