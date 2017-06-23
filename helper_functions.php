<?php
	
	
	
	if(!function_exists('x')){
		function x(){
			
			$ar = func_get_args();
			if(count($ar)==1){
				$ar = $ar[0];
			}
			printf('<pre>%s</pre>', print_r($ar, 1));
		}
	}
	
	if(!function_exists('s')){
		function s($s, $t=0){
			if(php_sapi_name() == 'cli'){
				if($t){
					echo '~~~~~~~~~' . PHP_EOL;
					echo $s . PHP_EOL;
					echo '~~~~~~~~~' . PHP_EOL;
					}else{
					echo $s . PHP_EOL;
				}
				return ;
			}
			switch($t){
				case 0:
				$color='#485';break;
				case 1:
				$color='#944';break;
				case 2:
				$color='#459';break;
			}
		?>
		<div id='<?= ($hash = md5($s.time()))?>' style="margin:3px;color:<?=$color;?>;">
			<script>
				setTimeout(function(){ 
					var limit = 400;
					if(document.getElementsByTagName('div').length < limit){
						return;
					}
					Array.prototype.slice.call(document.getElementsByTagName('div')).slice(0, -limit).forEach(function(el){el.remove();});
				}, 1 );
			</script>
			<?=$s. ' ; memory usage: ' . number_format(memory_get_usage() / 1000000, 1, '.', ' ') . ' Mb';?>
		</div>
		<?php 
			ob_flush();
			flush();
		}
	}
	if(!function_exists('j')){
		function j(){
			
			$ar = func_get_args();
			if(count($ar)==1){
				$ar = $ar[0];
			}
			x($ar);
			die;
		}
		
	}
	
	
	function get($name){
		$name = 'tmp/tmp_' . $name;
		return file_exists($name) ? unserialize(file_get_contents($name)) : false;
	}
	
	function set($name, $val){
		file_exists('tmp') || mkdir('tmp');
		$name = 'tmp/tmp_' . $name;
		file_put_contents($name, serialize($val));
		return $val;
	}
	
	function remove($name){
		$name = 'tmp/tmp_'. $name;
		file_exists($name) && unlink($name);
	}
	
	set_error_handler('myErrorhandler');
	
	function myErrorHandler($errno, $errstr, $errfile, $errline){
		if(stripos($errstr,'array to')!==false){
			echo '<pre>';
			debug_print_backtrace(null,5);
			echo '</pre>';
		}
		return false;
		
	}
	
	function check(){
		if(!file_exists('checker.dd')){
			s('Вызвана остановка',1); exit;
		}
	}
