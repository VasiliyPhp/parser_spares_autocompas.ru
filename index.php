<?php
	ignore_user_abort(true);
	error_reporting(E_ALL);
	ini_set('display_errors', true);
?>

<!doctype html>
<html>
	<head>
		<title><?=isset($_POST['cats']) && $_POST['cats'] ? $_POST['cats']
		. ' - поиск запчастей' : 'Парсер запчастей'?></title>
	</head>
	<body>
		<?php
			$allCats = [
			'VW',
			'OPEL',
			'MERCEDES-BENZ',
			'BMW',
			'FORD',
			'AUDI',
			'CADDILAC',
			'CHEVROLET',
			'CHRYSLER',
			'CITROEN',
			'DAEWOO',
			'DODGE',
			'FIAT',
			'GEELY',
			'HONDA',
			'HUMMER',
			'HYUNDAI',
			'INFINITI',
			'ISUZU',
			'JAGUAR',
			'JEEP',
			'KIA',
			'LAND ROVER',
			'LEXUS',
			'MAZDA',
			'MINI',
			'MITSUBISHI',
			'NISSAN',
			'PEUGEOT',
			'PORSCHE',
			'RENAULT',
			'SAAB',
			'SEAT',
			'SKODA',
			'SMART',
			'SSANG YONG',
			'SUBARU',
			'SUZUKI',
			'TOYOTA',
			'VOLVO',
			];
			require 'vendor/autoload.php';
			
			const SITE = 'https://www.autocompas.ru';
			if(isset($_POST['cats'])){
				set_time_limit(-1);
				touch('checker.dd');
				if(isset($_POST['id'])){
					id($_POST['id']);
				}
				// remove('cats');
				$needle = $_POST['cats'];
				// get_analogi(['',
				// '1431924',
				// 'bosch-1-987-432-097-filtr-vozdukh-vo-vnutrennom-prostranstve',
				// '1 987 432 097',
				// 'BOSCH',
				// ]);
				parse($needle);
			}
			else{
				echo "<form method=post target='_blank' >";
				foreach($allCats as $cat ){
					echo '<label><input name="cats" type=checkbox value="'.$cat.'" > '.$cat.'</label><br/>';
				}
				echo '<label> начальный id <input name=id  type=number /></label><br/>';
				echo '<input type=submit value=start />';
				echo '</form>';
				echo '<a href="stop.php" >stop</a></br>';
				// echo '<a onclick="return confirm(\'Are you shure\');" href="clear.php" >clear</a>';
			}
				