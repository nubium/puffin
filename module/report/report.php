<!doctype html>

<html lang="en">
<head>
	<meta charset="utf-8">

	<title>The HTML5 Herald</title>
	<meta name="description" content="The HTML5 Herald">
	<meta name="author" content="SitePoint">

	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<style type="text/css">
		img {
			width: 100%;
		}

		/* --------------------------------

		Main components

		-------------------------------- */
		header {
			position: relative;
			height: 160px;
			line-height: 160px;
			text-align: center;
		}

		header h1 {
			font-size: 22px;
			font-size: 1.375rem;
			color: #ffffff;
			font-weight: 300;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
		}

		@media only screen and (min-width: 768px) {
			header {
				height: 240px;
				line-height: 240px;
			}

			header h1 {
				font-size: 32px;
				font-size: 2rem;
			}
		}

		.cd-image-container {
			position: relative;
			width: 100%;
			margin: 0em auto;
		}

		.cd-image-container img {
			display: block;
		}

		.cd-image-label {
			position: absolute;
			bottom: 0;
			right: 0;
			color: #ffffff;
			padding: 1em;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			opacity: 0;
			-webkit-transform: translateY(20px);
			-moz-transform: translateY(20px);
			-ms-transform: translateY(20px);
			-o-transform: translateY(20px);
			transform: translateY(20px);
			-webkit-transition: -webkit-transform 0.3s 0.7s, opacity 0.3s 0.7s;
			-moz-transition: -moz-transform 0.3s 0.7s, opacity 0.3s 0.7s;
			transition: transform 0.3s 0.7s, opacity 0.3s 0.7s;
		}

		.cd-image-label.is-hidden {
			visibility: hidden;
		}

		.is-visible .cd-image-label {
			opacity: 1;
			-webkit-transform: translateY(0);
			-moz-transform: translateY(0);
			-ms-transform: translateY(0);
			-o-transform: translateY(0);
			transform: translateY(0);
		}

		.cd-resize-img {
			border-right: 2px solid rgba(255, 255, 255, 0.5);
			position: absolute;
			top: 0;
			left: 0;
			width: 0;
			height: 100%;
			overflow: hidden;
			/* Force Hardware Acceleration in WebKit */
			-webkit-transform: translateZ(0);
			-moz-transform: translateZ(0);
			-ms-transform: translateZ(0);
			-o-transform: translateZ(0);
			transform: translateZ(0);
			-webkit-backface-visibility: hidden;
			backface-visibility: hidden;
		}

		.cd-resize-img img {
			position: absolute;
			left: 0;
			top: 0;
			display: block;
			height: 100%;
			width: auto;
			max-width: none;
		}

		.cd-resize-img .cd-image-label {
			right: auto;
			left: 0;
		}

		.is-visible .cd-resize-img {
			width: 50%;
			/* bounce in animation of the modified image */
			-webkit-animation: cd-bounce-in 0.7s;
			-moz-animation: cd-bounce-in 0.7s;
			animation: cd-bounce-in 0.7s;
		}

		@-webkit-keyframes cd-bounce-in {
			0% {
				width: 0;
			}
			60% {
				width: 55%;
			}
			100% {
				width: 50%;
			}
		}

		@-moz-keyframes cd-bounce-in {
			0% {
				width: 0;
			}
			60% {
				width: 55%;
			}
			100% {
				width: 50%;
			}
		}

		@keyframes cd-bounce-in {
			0% {
				width: 0;
			}
			60% {
				width: 55%;
			}
			100% {
				width: 50%;
			}
		}

		.cd-handle {
			position: absolute;
			height: 44px;
			width: 44px;
			/* center the element */
			left: 50%;
			top: 50%;
			margin-left: -22px;
			margin-top: -22px;
			border-radius: 50%;
			background: #dc717d url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiIHdpZHRoPSIzMnB4IiBoZWlnaHQ9IjMycHgiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgMzIgMzIiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwb2x5Z29uIGZpbGw9IiNGRkZGRkYiIHBvaW50cz0iMTMsMjEgOCwxNiAxMywxMSAiLz48cG9seWdvbiBmaWxsPSIjRkZGRkZGIiBwb2ludHM9IjE5LDExIDI0LDE2IDE5LDIxICIvPjwvc3ZnPg==) no-repeat center center;
			cursor: move;
			box-shadow: 0 0 0 6px rgba(0, 0, 0, 0.2), 0 0 10px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.3);
			opacity: 0;
			-webkit-transform: translate3d(0, 0, 0) scale(0);
			-moz-transform: translate3d(0, 0, 0) scale(0);
			-ms-transform: translate3d(0, 0, 0) scale(0);
			-o-transform: translate3d(0, 0, 0) scale(0);
			transform: translate3d(0, 0, 0) scale(0);
		}

		.cd-handle.draggable {
			/* change background color when element is active */
			background-color: #445b7c;
		}

		.is-visible .cd-handle {
			opacity: 1;
			-webkit-transform: translate3d(0, 0, 0) scale(1);
			-moz-transform: translate3d(0, 0, 0) scale(1);
			-ms-transform: translate3d(0, 0, 0) scale(1);
			-o-transform: translate3d(0, 0, 0) scale(1);
			transform: translate3d(0, 0, 0) scale(1);
			-webkit-transition: -webkit-transform 0.3s 0.7s, opacity 0s 0.7s;
			-moz-transition: -moz-transform 0.3s 0.7s, opacity 0s 0.7s;
			transition: transform 0.3s 0.7s, opacity 0s 0.7s;
		}
	</style>
</head>
<body>
<div class="container-fluid">
	<div class="row">
		<div class="col-sm-12">
			<?php foreach ($results as $result) { ?>
			<div class="result">
				<div class="row">
					<div class="col-sm-12">
						<h2 id="<?= $result['identifier'] ?>"><?= number_format($result['deviation'], 16) ?>
							: <?= $result['identifier'] ?></h2>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-6">
						<img src="<?= $result['comparisonImage'] ?>" alt="Comparison">
					</div>
					<div class="col-sm-6">
						<figure class="cd-image-container">
							<img src="<?= $result['productionImage'] ?>" alt="Production">
							<span class="cd-image-label" data-type="original">Production</span>

							<div class="cd-resize-img"> <!-- the resizable image on top -->
								<img src="<?= $result['stagingImage'] ?>" alt="Staging">
								<span class="cd-image-label" data-type="modified">Staging</span>
							</div>

							<span class="cd-handle"></span> <!-- slider handle -->
						</figure> <!-- cd-image-container -->
					</div>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
<script src="https://cdn.rawgit.com/CodyHouse/image-comparison-slider/master/js/main.js"></script>
</body>
</html>
