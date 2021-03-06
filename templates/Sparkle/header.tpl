<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta http-equiv="Default-Style" content="text/css" />
	<if $settings['panel']['no_robots'] == '0'>
	<meta name="robots" content="noindex, nofollow, noarchive" />
	<meta name="GOOGLEBOT" content="nosnippet" />
	</if>
	<script type="text/javascript" src="js/jquery.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui.min.js"></script>
	<!--[if lt IE 9]><script src="js/html5shiv.js"></script><![endif]-->
	<if isset($intrafficpage)>
	<!--[if lt IE 9]><script language="javascript" type="text/javascript" src="js/excanvas.min.js"></script><![endif]-->
	<script language="javascript" type="text/javascript" src="js/jquery.jqplot.min.js"></script>
	<script language="javascript" type="text/javascript" src="js/plugins/jqplot.barRenderer.min.js"></script>
	<script language="javascript" type="text/javascript" src="js/plugins/jqplot.categoryAxisRenderer.min.js"></script>
	<script language="javascript" type="text/javascript" src="js/plugins/jqplot.pointLabels.min.js"></script>
	<link rel="stylesheet" type="text/css" href="css/jquery.jqplot.min.css" />
	<script language="javascript" type="text/javascript" src="templates/{$theme}/assets/js/traffic.js"></script>
	</if>
	<script language="javascript" type="text/javascript" src="templates/{$theme}/assets/js/jcanvas.min.js"></script>
	<script language="javascript" type="text/javascript" src="templates/{$theme}/assets/js/circular.js"></script>
	<if $settings['panel']['use_webfonts'] == '1'>
		<link href="//fonts.googleapis.com/css?family={$settings['panel']['webfont']}" rel="stylesheet">
	</if>
	<link href="templates/{$theme}/assets/css/main.css" rel="stylesheet" type="text/css" />
	<!--[if IE]><link rel="stylesheet" href="templates/{$theme}/css/main_ie.css" type="text/css" /><![endif]-->
	<link href="css/jquery-ui.min.css" rel="stylesheet" type="text/css"/>
	<script type="text/javascript" src="templates/{$theme}/assets/js/main.js"></script>
	<link href="templates/{$theme}/assets/img/favicon.ico" rel="icon" type="image/x-icon" />
	<title><if isset($userinfo['loginname']) && $userinfo['loginname'] != ''>{$userinfo['loginname']} - </if>Froxlor Server Management Panel</title>
	<style type="text/css">
	body {
        font-family: <if $settings['panel']['use_webfonts'] == '1'>{$webfont},</if> Verdana, Geneva, sans-serif;
	}
	</style>
</head>
<body>

<if isset($userinfo['loginname'])>
<header class="topheader">
	<hgroup>
		<h1>Froxlor Server Management Panel</h1>
	</hgroup>
	<a href="{$linker->getLink(array('section' => 'index'))}">
		<img src="{$header_logo}" alt="Froxlor Server Management Panel" class="small" />
	</a>
	<div class="topheader_navigation">
		<ul class="topheadernav">
			<if $settings['ticket']['enabled'] == 1>
				<li>
					<a href="{$linker->getLink(array('section' => 'tickets', 'page' => 'tickets'))}">
						<if 0 < $awaitingtickets>
							<img src="templates/{$theme}/assets/img/icons/menubar_tickets.png" alt="{$lng['menue']['ticket']['ticket']}" />
							<span class="countbubble">{$awaitingtickets}</span>
						<else>
							<img src="templates/{$theme}/assets/img/icons/menubar_tickets_null.png" alt="{$lng['menue']['ticket']['ticket']}" />
						</if>
					</a>
				</li>
			</if>
			<li>{$userinfo['loginname']}</li>
			<li><a href="{$linker->getLink(array('section' => 'index'))}">{$lng['panel']['dashboard']}</a></li>
			<li><a href="#">{$lng['panel']['options']}&nbsp;&#x25BE;</a>
				<ul>
					<li><a href="{$linker->getLink(array('section' => 'index', 'page' => 'change_password'))}">{$lng['login']['password']}</a></li>
					<li><a href="{$linker->getLink(array('section' => 'index', 'page' => 'change_language'))}">{$lng['login']['language']}</a></li>
					<if $settings['panel']['allow_theme_change_admin'] == '1' && $userinfo['adminsession'] == 1>
						<li><a href="{$linker->getLink(array('section' => 'index', 'page' => 'change_theme'))}">{$lng['panel']['theme']}</a></li>
					</if>
					<if $settings['panel']['allow_theme_change_customer'] == '1' && $userinfo['adminsession'] == 0>
						<li><a href="{$linker->getLink(array('section' => 'index', 'page' => 'change_theme'))}">{$lng['panel']['theme']}</a></li>
					</if>
				</ul>
			</li>
			<li><a href="{$linker->getLink(array('section' => 'index', 'action' => 'logout'))}" style="color: #cc0000;">{$lng['login']['logout']}</a></li>
		</ul>
	</div>
</header>
<div class="content">
	<nav id="sidenavigation">$navigation</nav>
	<div class="main" id="maincontent">
</if>
