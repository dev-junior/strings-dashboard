<!doctype html>
<html lang="en-US">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title> <?php echo $title_for_layout; ?> - Strings | Bitlancer</title>
  <link id="style" rel="stylesheet" type="text/css" href="/css/app.css">
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.1/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
  <?php
		echo $this->Html->meta('icon');
		echo $this->fetch('meta');
        echo $this->fetch('css');
        echo $this->fetch('script');
  ?>
</head>
<body class="loading">
  <header><?php echo $this->element('Layouts/header'); ?></header>
  <nav><?php echo $this->element('Layouts/nav'); ?></nav>
  <section> <!-- Content -->
    <?php echo $this->fetch('content'); ?>
  </section> <!-- /Content -->
<!-- Javascript -->
<script src="/js/jquery.dataTables.min.js"></script>
<script src="/js/jquery.fcbkcomplete.js"></script>
<script src="/js/app.js"></script>
<!--[if lt IE 9]>
<script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
</body>
</html>
