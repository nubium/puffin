<!DOCTYPE html>
<html>
<head>
	<title>Puffin Report</title>
</head>
<body>

<img src="http://www.thewebhatesme.com/wp-content/uploads/Puffin.png"/>

<?php foreach ($failedTests as $failedTest): ?>

	<div class="deviationimage">
		Deviation Image <br/>
		<img
			src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getDeviationImage())); ?>'/>
	</div>

	<div class="expectedimage">
		Expected Image <br/>
		<img
			src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getExpectedImage())); ?>'/>
	</div>

	<div class="currentimage">
		Current Image <br/>
		<img
			src='data:image/png;base64,<?php echo base64_encode(file_get_contents($failedTest->getCurrentImage())); ?>'/>
	</div>


<?php endforeach; ?>

</body>
</html>
