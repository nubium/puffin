<?php

/**
 * Class VisualChangesCest
 */
class VisualChangesCest
{
	/**
	 * @param FunctionalTester $I
	 */
	public function _before(FunctionalTester $I)
	{
	}


	/**
	 * @param FunctionalTester $I
	 */
	public function _after(FunctionalTester $I)
	{
	}


	/**
	 * @param FunctionalTester $I
	 */
	public function dontSeeVisualChanges(FunctionalTester $I)
	{
		$I->amOnPage('/');
		$I->dontSeeVisualChanges('same');

		// the test has to be called twice for comparison on the travis server
		$I->amOnPage('/');
		$I->dontSeeVisualChanges('same');
	}
}
