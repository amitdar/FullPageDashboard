<?php
    include_once 'api.php';
    
    $urls = $api->getUrls();
    $options = $api->getOptions();
    
    $seconds = (isset($options['speed'])) ? $options['speed'] : DEFAULT_ROTATE_SPEED;   
    
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
    
    if ($id > count($urls)) {
    	$id = 1;
    }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Full Page Dashboard</title>

    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="js/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.knob.min.js"></script>
    
    
    <script type="text/javascript">
    var rotateTime = <?php echo $seconds; ?>;
	var counter = rotateTime;
	var timer = null;
	
    function resize() {
    	$('.tab-content').css('height', $(window).height() - $('.nav-tabs').height() - 5);
    }

   	function nextTab() {
   	   	var next = $('.nav-tabs li.active').next('li');
   	   	if (next.text() == '+') {
   	   	   	$($('.nav-tabs li')[2]).find('a').click();
   	   	} else {
   	   	   	$('.nav-tabs li.active').next('li').find('a').click();
   	   	}
      	document.title = 'Full Page Dashboard - ' + $('.nav-tabs li.active a').text();
   	}

    function play() {
        timer = setInterval(function() { tick(); }, 1000);
        $('#pause-btn').removeClass('hidden');
    	$('#play-btn').addClass('hidden');
    }

    function stop() {
    	clearInterval(timer);
    	$('#pause-btn').addClass('hidden');
    	$('#play-btn').removeClass('hidden');
    }

    function initKnob(maxValue) {
    	$("#counter").knob({
        	width: 20,
        	height: 20,
        	displayInput: false,
        	min: 0,
        	max: maxValue,
        	readOnly: true
    	});
    }
    
    $(document).ready(function() {
    	<?php if (count($urls) > 1) : ?>
    	initKnob(rotateTime);
        play();
        document.title = 'Full Page Dashboard - ' + $('.nav-tabs li.active a').text();
        <?php endif; ?>
    	resize();
    });

    function tick() {
		$('#counter').val(counter).trigger('change');
    	counter--;
    	if (counter < 0) {
        	counter = rotateTime;
        	nextTab();
    	}
    }

    $(window).resize(function() {
		resize();
	});

    function deleteUrl() {
        var title = $('.nav-tabs li.active a').text();
        var id = $('.nav-tabs li.active a').attr('href').replace('#url', '');

        var r = confirm('Are you sure you would to delete "' + title + '"');
        if (r == true) {
        	$.ajax({
    		  	type: "POST",
    		  	url: 'api.php',
    		  	data: {'action': 'delete', 'title': title, 'id': id},
    		  	success: function(data, textStatus, jqXHR) {
    		  		window.location.reload()
    		    }
    		});
        }
    }

    function isNormalInteger(str) {
        var n = ~~Number(str);
        return String(n) === str && n >= 0;
    }
    
    function changeCounter() {
    	var newCounter = prompt("Select rotate time", rotateTime);
    	if (newCounter != null && isNormalInteger(newCounter)) {
    		newCounter = parseInt(newCounter);
    		$.ajax({
    		  	type: "POST",
    		  	url: 'api.php',
    		  	data: {'action': 'speed', 'value': newCounter},
    		  	success: function(data, textStatus, jqXHR) {
    		  		rotateTime = newCounter;
    		  		$('#counter').trigger('configure', {
    		  		    	"max": rotateTime,
					});
    		  		counter = 0;
    		    }
    		});
    	}
    }
    
	function addUrl() {
		var title = $('#add-title').val();
		var url = $('#add-url').val();

		if (title == '' || url == '') {
			alert('Title and URL cannot be empty');
		}

		$.ajax({
		  	type: "POST",
		  	url: 'api.php',
		  	data: {'action': 'add', 'title': title, 'url': url},
		  	success: function(data, textStatus, jqXHR) {
		  		window.location.reload()
		    }
		});
	}
	</script>
  </head>
  <body>
    <div>

      <!-- Nav tabs -->
      <ul class="nav nav-tabs" role="tablist">
        <?php if (count($urls) > 1) : ?>
        <li>
            <a href="javascript:void(0)" onclick="changeCounter()">
                <input type="text" id="counter" class="dial">
            </a>
        </li>
        <li>
            <a href="javascript:void(0)">
                <span id="pause-btn" onclick="stop()" class="glyphicon glyphicon-pause"></span>
                <span id="play-btn" onclick="play()" class="glyphicon glyphicon-play hidden"></span>
            </a>
        </li>
        <?php endif; ?>
        <?php foreach ($urls as $key => $url) : ?>
        <li role="presentation" <?php echo ($key == 0) ? 'class="active"' : ''; ?>><a href="#url<?php echo $key;?>" aria-controls="url<?php echo $key;?>" role="tab" data-toggle="tab"><?php echo $url['title']; ?></a></li>
    	<?php endforeach; ?>
        <li role="presentation"><a href="javascript:void(0)" data-toggle="modal" data-target="#addModal" aria-controls="add" role="tab" data-toggle="tab">+</a></li>
        <?php if (count($urls) > 1) : ?>
        <li role="presentation" class="pull-right" style="margin-top: 2px;"><a href="javascript:void(0)" onclick="deleteUrl()">
        <span id="play-btn" class="glyphicon glyphicon-trash"></span>
        </a></li>
        <?php endif; ?>
      </ul>
    
      <!-- Tab panes -->
      <div class="tab-content">
        <?php foreach ($urls as $key => $url) : ?>
        <div role="tabpanel" class="tab-pane <?php echo ($key == 0) ? 'active' : ''; ?>" id="url<?php echo $key; ?>">
            <iframe src="<?php echo $url['url']; ?>" id="frame-<?php echo $key; ?>" style="width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;z-index: 0;">
        		Your browser doesn't support iframes
        	</iframe>
        </div>
        <?php endforeach; ?>
      </div>
    
    </div>
    
    <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Add URL</h4>
          </div>
          <div class="modal-body">
            <form class="form-horizontal">
              <div class="form-group">
                <label for="add-title" class="col-sm-2 control-label">Title</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="add-title" placeholder="Title">
                </div>
              </div>
              <div class="form-group">
                <label for="add-url" class="col-sm-2 control-label">URL</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="add-url" placeholder="URL">
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="addUrl()">Add</button>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
