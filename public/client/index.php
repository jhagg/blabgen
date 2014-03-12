<?php
require __DIR__ . '/../bootstrap.php';
$video_feed_url = conf('cam.video_url');
?>
<!DOCTYPE HTML>
<html>
<head>
	<meta charset="utf-8">
	<title>Start</title>

	<link rel="stylesheet" href="select2/select2.css" />
	<link rel="stylesheet" href="css/screen.css" />
	<style type="text/css" media="screen">
<?php
if (
	conf('ui.logo_width' ) &&
	conf('ui.logo_height' ) &&
	conf('ui.logo_url' )
):
?>
.branding .company_name {
	width: <?php echo conf('ui.logo_width') ?>;
	height: <?php echo conf('ui.logo_height') ?>;
	background: transparent url('<?php echo conf('ui.logo_url') ?>') no-repeat 0 0;
}
<?php
endif;
?>
@font-face {
	font-family: normal_font;
	src: url('<?php echo conf('ui.font_normal_url') ?>');
}
@font-face {
	font-family: bold_font;
	src: url('<?php echo conf('ui.font_bold_url') ?>');
}
@font-face {
	font-family: xbold_font;
	src: url('<?php echo conf('ui.font_xbold_url') ?>');
}
body {
	font-family: normal_font;
}
label,
.label,
input[type=text],
.screen-start p.help,
.screen-done p.txt,
.screen-error p.txt,
.screen-photo h1,
.ctrl,
.countdown span,
.cam-ctrl span {
	font-weight: normal;
	font-family: bold_font;
}
.footer li .item,
.main h1,
screen-receiver input {
	font-weight: normal;
	font-family: xbold_font;
}
	</style>
</head>
<body>
	
<div id="doc">
	<header>
		<div class="branding">
			<h2 class="company_name">
				Axis Communications AB
			</h2>
		</div>
		<ul class="langs">
			<li>
				<a href="#" class="btn lang lang-sv_SE" data-locale="sv_SE">Svenska</a>
			</li>
			<li>
				<a href="#" class="btn lang lang-en_US" data-locale="en_US">English</a>
			</li>
		</ul>
		<!-- / langs -->
	</header>

	<div id="bd">
		<section class="main screen-start">
			<h1>
				<small class="translatable">Welcome to</small>
				<span class="translatable">
					Axis Communications
				</span>
			</h1>

			<p class="btn-start">
				<a href="" class="btn important btn-start translatable">Register</a>
			</p>
		</section>
		<!-- / start screen -->

		<section class="main screen-name">
			<form action="#">
				<div>
					<label for="data-name" id="data-name-label" class="label translatable">
							Your name
					</label>
					<input id="data-name" type="text" name="name" value=""/>
				</div>
			</form>
		</section>
		<!-- / name screen -->

		<section class="main screen-company">
			<form action="#">
				<div>
					<label for="data-company" class="label translatable">
						Your company (or leave blank)
					</label>
					<input id="data-company" type="text" name="company" value=""/>
				</div>
				<div>
					<label for="data-parking" class="label2 translatable">
						Your parking (or leave blank)
					</label>
					<input id="data-parking" type="text" name="parking" value=""/>
				</div>
			</form>
		</section>
		<!-- / company screen -->

		<section class="main screen-date">
			<h2 class="label translatable">
				Your last day of visit
			</h2>

			<ul class="dates">
				<li>
					<a class="btn">
						<strong class="wkd translatable">
							Monday
						</strong>
						<span class="d">
						</span>
						<span class="m">
						</span>
					</a>
				</li>
				<li>
					<a class="btn">
						<strong class="wkd translatable">
							Tuesday
						</strong>
						<span class="d">
						</span>
						<span class="m">
						</span>
					</a>
				</li>
				<li>
					<a class="btn">
						<strong class="wkd translatable">
							Wednesday
						</strong>
						<span class="d">
						</span>
						<span class="m">
						</span>
					</a>
				</li>
				<li>
					<a class="btn">
						<strong class="wkd translatable">
							Thursday
						</strong>
						<span class="d">
						</span>
						<span class="m">
						</span>
					</a>
				</li>
				<li>
					<a class="btn">
						<strong class="wkd translatable">
							Friday
						</strong>
						<span class="d">
						</span>
						<span class="m">
						</span>
					</a>
				</li>
			</ul>
			
		</section>
		<!-- / date screen -->

		<section class="main screen-receiver">
			<form action="#" id="receiver-f">
				<label class="label translatable">
					Your Axis host
				</label>
				<div class="receiver-w">
					<span id="none-match" style="display: none;" class="translatable">
						No matches.
					</span>
					<select multiple size="25"
						id="data-receiver"
						name="receiver">
					</select>
				</div>
			</form>
		</section>
		<!-- / receiver screen -->

		<section class="main screen-photo">
			<h1 class="translatable screen-title">
				Your picture
			</h1>
			<form action="#">
				<p class="cam-ctrl">
					<a class="btn up" id='cam-up'>
						↑	
					</a>
					<span class="translatable">
						Adjust camera
					</span>
					<a class="btn down" id='cam-down'>
						↓
					</a>
				</p>
				<div class="video-w">
					<div class="video">
					<img src='<?php echo $video_feed_url ?>' alt='' width='420' height='315' id='picture-img' data-video-src='<?php echo $video_feed_url ?>' />
					</div>
					<div class="ctrl">
						<p class="snap">
							<a class="btn btn-snap translatable">
								Take picture
							</a>
						</p>
						<p class="countdown">
							<span>3</span>
							<span>2</span>
							<span>1</span>
						</p>
						<p class="delete">
							<span class="translatable">Not OK?</span>
							<a class="btn delete btn-delete translatable">
								Delete picture
							</a>
						</p>
					</div>
				</div>
			</form>
		</section>
		<!-- / photo screen -->

		<section class="main screen-submit-wait">
			<p class="txt translatable">
				Registering ...
			</p>
		</section>
		<!-- / submit-wait screen -->

		<section class="main screen-done">
			<h1 class="translatable">
				Thank you!
			</h1>

			<p class="txt translatable">
<?php
if ( conf('ui.thankyou_msg') ):
	echo conf('ui.thankyou_msg');
else:
?>
				You can now pick up your visitor's card at the reception desk.
<?php
endif;
?>
			</p>

			<p class="btn-done">
				<a href="" class="btn important btn-done translatable">
					New visitor
				</a>
			</p>
		</section>
		<!-- / done screen -->

		<section class="main screen-error">
			<h1 class="translatable">
				Oops.
			</h1>

			<p class="txt translatable">
				An error has occured. The system needs to be restarted. Please contact the reception desk.
			</p>
		</section>
		<!-- / error screen -->

		<!-- navigation buttons -->
		<ul class="nav">
			<li class="abort">
				<a class="btn abort translatable">
					Cancel
				</a>
			</li>
			<li class="prev">
				<a class="btn translatable">
					← Previous
				</a>
			</li>
			<li class="next">
				<a class="btn important translatable">
					Next →
				</a>
			</li>
		</ul>
		<!-- / nav -->
	</div>
	<!-- / bd -->

	<footer>
		<ul class="steps">
			<li class="name one next">
				<span class='item' data-screen="name">
					<strong>1</strong>
					<span class="translatable">
						Your<br>name
					</span>
				</span>
			</li>
			<li class="company two next">
				<span class='item' data-screen="company">
					<strong>2</strong>
					<span class="translatable">
						Your<br>company
					</span>
				</span>
			</li>
			<li class="date three next">
				<span class='item' data-screen="date">
					<strong>3</strong>
					<span class="translatable">
						Visiting<br>date
					</span>
				</span>
			</li>
			<li class="receiver four next">
				<span class='item' data-screen="receiver">
					<strong>4</strong>
					<span class="translatable">
						Your<br>Axis host
					</span>
				</span>
			</li>
			<li class="photo five next">
				<span class='item' data-screen="photo">
					<strong>5</strong>
					<span class="translatable">
						Your<br>picture
					</span>
				</span>
			</li>
			<li class="done six next">
				<span class='item' data-screen="done">
					<strong>6</strong>
					<span class="translatable">
						Pick up<br>your card
					</span>
				</span>
			</li>
		</ul>
		<!-- / step nav -->
	</footer>

	<!-- flash placeholder -->
	<div id="flash"></div>
	<!-- / flash placeholder -->

</div>
<!-- / doc -->


<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.3.custom.min.js"></script>
<script src="js/jquery.blockUI-2.66.0.js"></script>

<script src="select2/select2.js"></script>
<!-- script src="select2/select2_locale_sv.js"></script --!>
<script src="js/select_config.js"></script>

<script src="js/gettext.js"></script>
<script src="js/core.js"></script>

</body>
</html>
